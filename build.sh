#!/bin/bash
set -euo pipefail

# JMCP Joomla Component Build Script
# Packages the com_jmcp extension into a deployable ZIP.

echo "Building com_jmcp extension package..."

required=(jmcp.xml script.php update.xml admin site LICENSE)
for path in "${required[@]}"; do
    if [ ! -e "${path}" ]; then
        echo "Missing required path: ${path}"
        exit 1
    fi
done

if [ -f "com_jmcp.zip" ]; then
    echo "Removing old com_jmcp.zip..."
    rm com_jmcp.zip
fi

zip -r com_jmcp.zip jmcp.xml script.php update.xml admin site LICENSE

if [ ! -f "com_jmcp.zip" ]; then
    echo "Build failed: com_jmcp.zip was not created."
    exit 1
fi

echo "Build success! com_jmcp.zip is ready for installation ($(du -h com_jmcp.zip | cut -f1))."
