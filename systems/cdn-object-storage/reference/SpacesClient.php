<?php
/**
 * Lightweight DigitalOcean Spaces (S3-compatible) client.
 * Uses AWS Signature Version 4. No SDK, no composer. Requires: curl, simplexml.
 * Works identically against AWS S3, Cloudflare R2, Backblaze B2 (S3 API),
 * MinIO — just change endpoint/region/bucket.
 *
 * Source: Alta Apps production (apps.altajan.com). Zero dependencies, PHP 7.4+.
 */
class SpacesClient {
    private string $key;
    private string $secret;
    private string $endpoint;
    private string $region;
    private string $bucket;

    public function __construct(
        string $key,
        string $secret,
        string $endpoint = 'https://sfo3.digitaloceanspaces.com',
        string $region = 'sfo3',
        string $bucket = 'demelos'
    ) {
        $this->key = $key;
        $this->secret = $secret;
        $this->endpoint = rtrim($endpoint, '/');
        $this->region = $region;
        $this->bucket = $bucket;
    }

    private function sign(string $method, string $path, array $query = [], array $headers = [], ?string $body = null): array {
        $url = "{$this->endpoint}/{$this->bucket}{$path}";
        if ($query) {
            $url .= '?' . http_build_query($query);
        }

        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $date = gmdate('Ymd\THis\Z');
        $dateShort = substr($date, 0, 8);

        $headers['Host'] = $host;
        $headers['X-Amz-Date'] = $date;
        $headers['X-Amz-Content-SHA256'] = hash('sha256', $body ?? '');

        // Build canonical headers
        $canonicalHeaders = '';
        $signedHeaderNames = [];
        $lowerHeaders = [];
        foreach ($headers as $k => $v) {
            $lk = strtolower($k);
            $lowerHeaders[$lk] = trim($v);
            $signedHeaderNames[] = $lk;
        }
        sort($signedHeaderNames);
        foreach ($signedHeaderNames as $h) {
            $canonicalHeaders .= $h . ':' . $lowerHeaders[$h] . "\n";
        }
        $signedHeadersStr = implode(';', $signedHeaderNames);

        // Build canonical query string (sorted by key)
        $canonicalQuery = '';
        if ($query) {
            ksort($query);
            $pairs = [];
            foreach ($query as $k => $v) {
                $pairs[] = rawurlencode($k) . '=' . rawurlencode($v);
            }
            $canonicalQuery = implode('&', $pairs);
        }

        $canonicalUri = '/' . $this->bucket . ($path === '' ? '/' : $path);
        $canonicalRequest = implode("\n", [
            $method,
            $canonicalUri,
            $canonicalQuery,
            $canonicalHeaders,
            $signedHeadersStr,
            $headers['X-Amz-Content-SHA256'],
        ]);

        $credentialScope = "{$dateShort}/{$this->region}/s3/aws4_request";
        $stringToSign = implode("\n", [
            'AWS4-HMAC-SHA256',
            $date,
            $credentialScope,
            hash('sha256', $canonicalRequest),
        ]);

        $kDate = hash_hmac('sha256', $dateShort, 'AWS4' . $this->secret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $auth = "AWS4-HMAC-SHA256 Credential={$this->key}/{$credentialScope}, SignedHeaders={$signedHeadersStr}, Signature={$signature}";

        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "$k: $v";
        }
        $curlHeaders[] = "Authorization: $auth";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $curlHeaders);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        return ['code' => $httpCode, 'body' => $response, 'error' => $error];
    }

    /** List objects under a prefix. delimiter '/' = one level (files + folders); '' = recursive. */
    public function listObjects(string $prefix = '', string $delimiter = '/'): array {
        $query = [
            'list-type' => '2',
            'prefix' => $prefix,
            'delimiter' => $delimiter,
        ];
        $res = $this->sign('GET', '/', $query);
        if ($res['code'] !== 200) {
            return ['error' => 'List failed', 'code' => $res['code'], 'body' => $res['body']];
        }
        $xml = simplexml_load_string($res['body']);
        $files = [];
        $folders = [];

        if ($xml->Contents) {
            foreach ($xml->Contents as $item) {
                $key = (string)$item->Key;
                if ($key === $prefix) continue;
                $files[] = [
                    'key' => $key,
                    'name' => basename($key),
                    'size' => (int)$item->Size,
                    'last_modified' => (string)$item->LastModified,
                    'etag' => trim((string)$item->ETag, '"'),
                ];
            }
        }
        if ($xml->CommonPrefixes) {
            foreach ($xml->CommonPrefixes as $item) {
                $p = (string)$item->Prefix;
                $folders[] = [
                    'key' => $p,
                    'name' => rtrim(basename($p), '/'),
                ];
            }
        }
        return ['files' => $files, 'folders' => $folders];
    }

    /** Time-limited download URL for a PRIVATE object. Default 1 hour. */
    public function getSignedUrl(string $key, int $expires = 3600): string {
        $path = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        $url = "{$this->endpoint}/{$this->bucket}{$path}";

        $host = parse_url($this->endpoint, PHP_URL_HOST);
        $date = gmdate('Ymd\THis\Z');
        $dateShort = substr($date, 0, 8);
        $credentialScope = "{$dateShort}/{$this->region}/s3/aws4_request";

        $params = [
            'X-Amz-Algorithm' => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential' => $this->key . '/' . $credentialScope,
            'X-Amz-Date' => $date,
            'X-Amz-Expires' => (string)$expires,
            'X-Amz-SignedHeaders' => 'host',
        ];
        ksort($params);
        $canonicalQuery = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $canonicalUri = '/' . $this->bucket . $path;
        $canonicalRequest = "GET\n{$canonicalUri}\n{$canonicalQuery}\nhost:{$host}\n\nhost\nUNSIGNED-PAYLOAD";

        $stringToSign = "AWS4-HMAC-SHA256\n{$date}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $kDate = hash_hmac('sha256', $dateShort, 'AWS4' . $this->secret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', 's3', $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);
        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        return $url . '?' . $canonicalQuery . '&X-Amz-Signature=' . $signature;
    }

    public function deleteObject(string $key): array {
        $path = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        $res = $this->sign('DELETE', $path);
        return ['success' => $res['code'] === 204 || $res['code'] === 200, 'code' => $res['code']];
    }

    public function putObject(string $key, string $content, string $contentType = 'application/octet-stream'): array {
        $path = '/' . implode('/', array_map('rawurlencode', explode('/', $key)));
        $res = $this->sign('PUT', $path, [], ['Content-Type' => $contentType], $content);
        return ['success' => $res['code'] === 200, 'code' => $res['code'], 'body' => $res['body']];
    }
}
