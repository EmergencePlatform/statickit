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

## Setup

1. Edit [`php-config/Git.config.d/content.php`](./php-config/Git.config.d/content.php) to define a source called `content` that connects with your static project. Do not define any path mappings though, as this source will not be pulled into the site.
1. Initialize the repository for that source via the `/site-admin/sources` UI and get the desired commit checked out
1. Configure a Slack channel to post build results to in [`php-config/Emergence/StaticKit/BuildWorkflow.config.d/chat-channel.php`](./php-config/Emergence/StaticKit/BuildWorkflow.config.d/chat-channel.php)

## Running builds

### Manually

Execute via a shell on the server:

```bash
echo 'Emergence\StaticKit\BuildWorkflow::run();' | sudo emergence-shell mysite-handle
```

### Automatically

1. Populate a random webhook secret string in `php-config/Emergence/GitHub/Connector.config.php`
1. Configure a webhook for `push` events in GitHub or Gitea:
   - **URL**: `http://example.org/connectors/github/webhooks`
   - **Content type**: `application/json`
   - **Secret**: *use from step 1*
