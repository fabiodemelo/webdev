# Block: <NAME>

<One-paragraph description: what the block looks like, what it does, responsive behavior.>

**Preview:** <short visual note or link to preview.png>

---

## Integration Prompt

> Paste everything below this line into the target project.

---

You are given a task to integrate an existing React component in the codebase.

The codebase should support:
- shadcn project structure
- Tailwind CSS
- TypeScript

If it doesn't, provide instructions on how to setup the project via shadcn CLI, install Tailwind, or TypeScript.

Determine the default path for components and styles.
If the default path for components is not `/components/ui`, provide instructions on why it's important to create this folder.

Copy-paste this component into the `/components/ui` folder:

```tsx
// <component-file-name>.tsx
<COMPONENT CODE — full, no placeholders, no TODOs>
```

### Demo

```tsx
// demo.tsx
<USAGE EXAMPLE with realistic sample data>
```

### Install NPM dependencies

```bash
npm install <deps>
```

### Implementation Guidelines

1. Analyze the component structure and identify all required dependencies.
2. Review the component's arguments and state.
3. Identify any required context providers or hooks and install them.
4. Questions to ask:
   - What data/props will be passed to this component?
   - Are there any specific state management requirements?
   - Are there any required assets (images, icons, etc.)?
   - What is the expected responsive behavior?
   - What is the best place to use this component in the app?

### Steps to integrate

0. Copy-paste all the code above into the correct directories.
1. Install external dependencies.
2. Fill image assets with stock images you know exist (Unsplash / randomuser.me).
3. Use `lucide-react` icons for SVGs or logos if the component requires them.

---

## Block Metadata

| Field | Value |
|-------|-------|
| Category | <category> |
| Deps | <npm deps> |
| Data shape | <props / data model> |
| Responsive | <breakpoint behavior> |
| States | <loading / empty / error / static> |
| Assets | <images / icons needed> |
