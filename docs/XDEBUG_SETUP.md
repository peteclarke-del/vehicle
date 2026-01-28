# Xdebug Setup Guide for Firefox + VS Code

## Overview
Xdebug is now configured to work with Firefox and VS Code for debugging PHP code in Docker containers.

## Configuration Details

### Xdebug Settings
- **Mode**: `debug,develop,coverage`
- **Port**: `9003` (Xdebug 3 default)
- **IDE Key**: `PHPSTORM` (compatible with all IDEs)
- **Client Host**: `host.docker.internal` (Docker → Host)

### Path Mappings
- **Container**: `/var/www/html`
- **Local**: `${workspaceFolder}/backend`

## Firefox Setup

### 1. Install Xdebug Helper Extension
Install the Firefox extension:
- **Xdebug Helper**: https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-for-firefox/

### 2. Configure Extension
1. Click the Xdebug Helper icon in Firefox toolbar
2. Go to **Options/Settings**
3. Set **IDE Key** to: `PHPSTORM`
4. Save settings

### 3. Enable Debugging
When you want to debug:
1. Click the Xdebug Helper icon
2. Select **Debug** (bug icon turns green)
3. Load/refresh the page you want to debug

## VS Code Setup

### 1. Install PHP Debug Extension
Install the VS Code extension:
- **PHP Debug** by Xdebug (`xdebug.php-debug`)

### 2. Start Debugging
1. Open VS Code in the project root
2. Set breakpoints in PHP files (`backend/src/**/*.php`)
3. Press `F5` or go to **Run and Debug** → **Listen for Xdebug (Docker)**
4. Load the page in Firefox with Xdebug Helper enabled

### 3. Verify Connection
- Check the **Debug Console** in VS Code for connection messages
- Xdebug logs are available at `/tmp/xdebug.log` in the PHP container

## Rebuild Containers

After configuration changes, rebuild:

```bash
# Rebuild PHP container with new Xdebug config
make build-backend

# Or manually
docker-compose build php
docker-compose up -d php

# Verify Xdebug is loaded
docker-compose exec php php -v
# Should show: "with Xdebug v3.x.x"

docker-compose exec php php -m | grep xdebug
# Should show: "xdebug"
```

## Testing the Setup

### 1. Simple Test
Add a breakpoint in `backend/src/Controller/VehicleController.php`:

```php
public function index(): JsonResponse
{
    $vehicles = []; // <-- Set breakpoint here
    // ... rest of code
}
```

### 2. Load Page
1. Start VS Code debugger (`F5`)
2. Enable Xdebug in Firefox (green bug icon)
3. Navigate to: http://localhost:3000
4. Login and go to Vehicles page
5. VS Code should pause at your breakpoint

## Troubleshooting

### Xdebug Not Connecting

**Check Xdebug is enabled:**
```bash
docker-compose exec php php -i | grep xdebug.mode
# Should show: xdebug.mode => debug,develop,coverage
```

**Check port is exposed:**
```bash
docker-compose ps
# Should show: 0.0.0.0:9003->9003/tcp for php service
```

**Check VS Code is listening:**
- Debug console should show: "Listening on port 9003"

**Check Firefox extension:**
- Xdebug Helper icon should be green when debugging
- Check extension settings: IDE Key = PHPSTORM

### Breakpoints Not Hit

**Path mapping issue:**
- Verify `/var/www/html` maps to `backend/` folder
- Check `.vscode/launch.json` pathMappings

**Xdebug not starting:**
```bash
# Check Xdebug log
docker-compose exec php cat /tmp/xdebug.log
```

**Wrong IDE key:**
```bash
# Verify environment
docker-compose exec php env | grep XDEBUG
# Should show: XDEBUG_SESSION=PHPSTORM
```

### Performance Issues

If Xdebug slows down the application:

**Disable when not debugging:**
```bash
# In docker-compose.override.yml, change:
XDEBUG_MODE: coverage  # or 'off'
```

**Or use start_with_request=trigger:**
```bash
# Only starts when cookie/header is present (Firefox extension does this)
```

## Alternative: Chrome Setup

If using Chrome instead of Firefox:

1. Install **Xdebug Helper** extension for Chrome
2. Set IDE Key to `PHPSTORM`
3. Click extension icon → Debug (green)
4. Same VS Code setup applies

## CLI Debugging

To debug CLI scripts (e.g., console commands):

```bash
# Run with Xdebug enabled
docker-compose exec -e XDEBUG_SESSION=PHPSTORM php php bin/console cache:clear

# Or set in override file and restart
```

## References

- Xdebug 3 Documentation: https://xdebug.org/docs/
- VS Code PHP Debug: https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug
- Xdebug Helper Firefox: https://addons.mozilla.org/en-US/firefox/addon/xdebug-helper-for-firefox/
