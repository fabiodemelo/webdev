# System: CDN / Object Storage Integration

Drop-in file storage for any project: private-by-default objects on S3-compatible storage (DigitalOcean Spaces) with a built-in CDN edge, **pre-signed URLs** for reads, MySQL metadata mirroring, per-project key namespacing, and soft-delete-to-trash. No SDK required for PHP — a single ~200-line dependency-free client does everything with raw AWS Signature v4 over cURL. The same storage is reached from Node/Python via the standard S3 SDK with a custom endpoint.

**Type:** shared backend integration module (storage client + metadata schema + upload/serve/delete recipes). Used by any feature that stores files — e.g. [support-ticket-system](../support-ticket-system/SPEC.md) attachments, RFP/employee/contract docs, avatars, public brand assets.

**Reference stack:** DigitalOcean Spaces (`sfo3`, bucket `demelos`, CDN `*.cdn.digitaloceanspaces.com`) + PHP (zero-dep client) + MySQL. Works unchanged against **AWS S3, Cloudflare R2, Backblaze B2, MinIO** — swap endpoint/region/bucket.

> **Source build:** Alta Apps production (`apps.altajan.com`). Reference client: [reference/SpacesClient.php](reference/SpacesClient.php) · metadata schema: [reference/schema.sql](reference/schema.sql).

---

## Integration Prompt

> Paste everything below this line into the target project. Swap bucket/endpoint/prefix + create your own Spaces key.

---

You are given a task to add **CDN / object storage** (file upload, private download via signed URLs, public CDN assets) to a project.

### 1. What the storage is

- **Provider:** DigitalOcean Spaces (S3-compatible object storage + built-in CDN).
- **Endpoint/region:** `https://sfo3.digitaloceanspaces.com` / `sfo3`. **Bucket:** `demelos`.
- **CDN edge (cached public assets):** `https://demelos.sfo3.cdn.digitaloceanspaces.com`.
- **Private by default** — reads go through **pre-signed URLs** (time-limited, generated server-side). Public objects via `x-amz-acl: public-read` on upload.
- **No SDK for PHP** — one dependency-free class (AWS SigV4 over cURL). Node/Python use the official S3 SDK with a custom endpoint.

### 2. Credentials (`.env`, never in code)

```env
DO_SPACES_KEY=<access key>
DO_SPACES_SECRET=<secret key>
DO_SPACES_ENDPOINT=https://sfo3.digitaloceanspaces.com
DO_SPACES_REGION=sfo3
DO_SPACES_BUCKET=demelos
DO_SPACES_PREFIX=apps/        # per-project namespace inside the bucket
```
**New project:** create a fresh Spaces key (DO panel → API → Spaces Keys) and pick your own `DO_SPACES_PREFIX` (e.g. `proposaldesk/`) so projects never collide — or a separate bucket per project (same code, change `DO_SPACES_BUCKET`).

### 3. Key layout convention

No real folders in S3 — "folders" are key prefixes:
```
apps/                        ← project prefix (DO_SPACES_PREFIX)
├── contract/                ← one folder per module/entity type
├── RFPs/
│   └── 464/                 ← one folder per record id
│       └── 1720558292_solicitation.pdf
├── tickets/
└── trash/                   ← soft-delete target (move, don't delete)
```
- Stored filename: `{unix_timestamp}_{sanitized_original_name}` — collision-proof, still readable.
- Empty "folder" = zero-byte object, key ending `/`, content-type `application/x-directory`.
- Soft delete = copy to `trash/` prefix + delete original (S3 has no move).

### 4. Metadata in MySQL, bytes in Spaces

Two tables (full DDL in [reference/schema.sql](reference/schema.sql)):
- **`files`** — general browser metadata: `object_key` (unique), `name`, `size`, `content_type`, `path`, `tags`, `notes`, `uploaded_by`, `original_key` + `trashed_at` (restore/soft-delete).
- **`entity_files`** — attachments pinned to a record: `entity_type` (`rfp`/`employee`/`contract`/…), `entity_id`, `original_name`, `stored_name`, `object_key`, `file_size`, `mime_type`, `uploaded_by`.

**Rule: every byte-level op on Spaces is mirrored by a row op in MySQL, in the same request.** Listing screens read MySQL (fast, searchable); Spaces is touched only for upload, signed-URL download, and delete.

### 5. The client

PHP: [reference/SpacesClient.php](reference/SpacesClient.php) — complete production class (AWS SigV4, cURL, simplexml). Methods: `putObject`, `getSignedUrl`, `deleteObject`, `listObjects`. Construct from env:
```php
require_once 'SpacesClient.php';
$spaces = new SpacesClient(
    getenv('DO_SPACES_KEY'), getenv('DO_SPACES_SECRET'),
    getenv('DO_SPACES_ENDPOINT') ?: 'https://sfo3.digitaloceanspaces.com',
    getenv('DO_SPACES_REGION') ?: 'sfo3',
    getenv('DO_SPACES_BUCKET') ?: 'demelos'
);
$prefix = getenv('DO_SPACES_PREFIX') ?: 'apps/';
```

### 6. Recipes

**Upload a browser file:**
```php
$f = $_FILES['file'];
if ($f['size'] > 50 * 1024 * 1024) die('Max 50MB');                 // size cap
$safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $f['name']);         // sanitize
$key  = $prefix . 'RFPs/' . $recordId . '/' . time() . '_' . $safe; // timestamped key
$mime = mime_content_type($f['tmp_name']) ?: 'application/octet-stream';

$res = $spaces->putObject($key, file_get_contents($f['tmp_name']), $mime);
if (!$res['success']) die('Upload failed: HTTP ' . $res['code']);

$stmt = $db->prepare("INSERT INTO entity_files (entity_type, entity_id, original_name, stored_name, object_key, file_path, file_size, mime_type, uploaded_by, uploaded_by_name) VALUES (?,?,?,?,?,?,?,?,?,?)");
$stmt->execute(['rfp', $recordId, $f['name'], basename($key), $key, $key, $f['size'], $mime, $userId, $userName]);
```

**Serve/download a private file — never proxy bytes through the app; redirect to a signed URL:**
```php
$url = $spaces->getSignedUrl($objectKey, 3600);   // valid 1 hour
header('Location: ' . $url); exit;
// For <img src>/previews: return the signed URL as JSON and use it directly in src.
```

**Public CDN asset (logos, theme art — never need auth):** upload with `x-amz-acl: public-read`, then the permanent cached URL is `https://demelos.sfo3.cdn.digitaloceanspaces.com/' . $key`.

**List a folder:** `$spaces->listObjects($prefix.'RFPs/464/', '/')` → `['files'=>[...], 'folders'=>[...]]`. **Recursive:** empty delimiter `''`. **Empty folder:** `putObject($prefix.'newfolder/', '', 'application/x-directory')`.

**Soft delete (trash):** copy to `trash/` (S3 has no move) → delete original → `UPDATE files SET object_key=?, original_key=?, trashed_at=NOW()`. **Hard delete:** `deleteObject($key)` + delete the MySQL row.

### 7. Non-PHP (Node / Python)

Same storage, standard S3 API with a custom endpoint:
```js
// Node — @aws-sdk/client-s3 + @aws-sdk/s3-request-presigner
const s3 = new S3Client({ endpoint: 'https://sfo3.digitaloceanspaces.com', region: 'sfo3',
  credentials: { accessKeyId: process.env.DO_SPACES_KEY, secretAccessKey: process.env.DO_SPACES_SECRET }, forcePathStyle: false });
await s3.send(new PutObjectCommand({ Bucket: 'demelos', Key: 'myproject/img/logo.png', Body: buf, ContentType: 'image/png' }));
const url = await getSignedUrl(s3, new GetObjectCommand({ Bucket: 'demelos', Key: 'myproject/img/logo.png' }), { expiresIn: 3600 });
```
```python
# Python — boto3
s3 = boto3.client('s3', endpoint_url='https://sfo3.digitaloceanspaces.com', region_name='sfo3',
    aws_access_key_id=os.environ['DO_SPACES_KEY'], aws_secret_access_key=os.environ['DO_SPACES_SECRET'])
s3.upload_fileobj(fh, 'demelos', 'myproject/docs/report.pdf', ExtraArgs={'ContentType': 'application/pdf'})
url = s3.generate_presigned_url('get_object', Params={'Bucket': 'demelos', 'Key': 'myproject/docs/report.pdf'}, ExpiresIn=3600)
```

### 8. Rules & gotchas (production-learned)

1. **Never store secrets in code** — `.env` only, outside webroot or blocked by the server.
2. **Never proxy downloads through your app** — always redirect to a signed URL.
3. **Signed-URL expiry:** 3600s for user downloads, 300s for internal copy ops.
4. **Sanitize filenames** + prefix `time().'_'` — collisions + unicode names bite otherwise.
5. **Always mirror metadata in MySQL** in the same request; MySQL is the listing source of truth.
6. **Namespace per project** with `DO_SPACES_PREFIX` — one bucket serves many apps.
7. **Private by default + signed URLs.** Only immutable public assets get `public-read`, served via the CDN host for edge caching.
8. **Size caps + MIME allowlist at upload** (images: png/jpg/webp/svg; docs: pdf/docx/xlsx; reject the rest).
9. S3 has **no rename/move** — always copy + delete.
10. Client works unchanged against **AWS S3, Cloudflare R2, MinIO, Backblaze** — swap endpoint/region/bucket to migrate.

---

## System Metadata

| Field | Value |
|-------|-------|
| Category | Infrastructure / file storage / CDN |
| Provider | DigitalOcean Spaces (S3-compatible) + CDN edge; portable to S3/R2/B2/MinIO |
| Backend | PHP (zero-dep SigV4 client) or Node/Python (S3 SDK) + MySQL metadata |
| Key concepts | Private-by-default + pre-signed URLs, MySQL-mirrors-bytes, per-project key prefix, soft-delete-to-trash, public assets via CDN host |
| Used by | [support-ticket-system](../support-ticket-system/SPEC.md) attachments, file browsers, entity documents, brand assets |
| Reference files | [SpacesClient.php](reference/SpacesClient.php), [schema.sql](reference/schema.sql) |
| Source build | Alta Apps (`apps.altajan.com`) |
