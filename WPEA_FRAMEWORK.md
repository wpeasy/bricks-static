# WP Easy Admin Framework

## Setup Instructions

**IMPORTANT:** Before building any Svelte UI components, you MUST download and integrate the WPEA framework.

### Step 1: Download the Library

Clone or download the WPEA library from GitHub into the `lib/wpea` directory:

```bash
git clone https://github.com/wpeasy/wpeasy-admin-framework lib/wpea
```

Or download as ZIP from: https://github.com/wpeasy/wpeasy-admin-framework

### Step 2: Read Framework Documentation

After downloading, read the framework documentation:

```
lib/wpea/claude.md
```

This file contains all CSS variables, component patterns, and usage instructions that ALL Svelte components must follow.

### Step 3: Integration

All Svelte components in this project MUST:
- Use WPEA CSS variables for colors, spacing, and typography
- Follow WPEA component patterns and naming conventions
- Import WPEA styles where needed