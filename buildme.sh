#!/bin/bash

set -ax;
#set -e;

echo >&2 "$0: running npm create-start";
npx create-react-app client --template typescript
echo >&2 "$0: running npm start";
npm start
echo >&2 "$0: running npm run build";
npm run build
echo >&2 "$0: running npm test";
npm test

