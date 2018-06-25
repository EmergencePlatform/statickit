# statickit
A toolkit for developing static websites

## Project standards

- Every project should use NPM to manage dependencies and build processes
- `npm install` should handle installing all dependencies
- `npm run build` should handle creating/updating a `build/` tree

    To define build script, add a `"scripts"` section to `package.json`:
    ```json
    "scripts": {
        "build": "grunt build"
    }
    ```
