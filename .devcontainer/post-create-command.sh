#!/bin/bash
# Install nodejs and npm on the Alpine Linux image
apk add nodejs npm
# Install npm packages
npm install
# Run npm build
npm run build:dev