#!/usr/bin/env bash
# Build and package stirlingmerge for production deployment
# Usage: ./package-app.sh
set -e

APP=stirlingmerge
VERSION=$(grep -oP '(?<=<version>)[^<]+' stirlingmerge/appinfo/info.xml)
OUTFILE="${APP}-${VERSION}.tar.gz"

echo "Building JS assets..."
docker run --rm \
  -v "$(pwd)/stirlingmerge:/app" \
  -w /app \
  node:20-alpine \
  sh -c "npm install --legacy-peer-deps 2>/dev/null && npm run build"

echo "Packaging ${OUTFILE}..."
tar -czf "$OUTFILE" \
  --exclude="${APP}/node_modules" \
  --exclude="${APP}/src" \
  --exclude="${APP}/package.json" \
  --exclude="${APP}/package-lock.json" \
  --exclude="${APP}/webpack.config.js" \
  --exclude="${APP}/composer.json" \
  "${APP}/"

echo "Done: ${OUTFILE} ($(du -sh "$OUTFILE" | cut -f1))"
echo ""
echo "Install on target NC server:"
echo "  docker cp ${OUTFILE} <nc-container>:/var/www/html/apps/"
echo "  docker exec <nc-container> tar -xzf /var/www/html/apps/${OUTFILE} -C /var/www/html/apps/"
echo "  docker exec --user www-data <nc-container> php occ app:enable ${APP}"
echo "  docker exec --user www-data <nc-container> php occ config:app:set ${APP} stirling_url --value='http://STIRLING_HOST:8080'"
echo "  docker exec --user www-data <nc-container> php occ config:app:set ${APP} stirling_api_key --value='YOUR_KEY'"
echo ""
echo "Or configure via: Admin settings → Additional settings → Merge to PDF"
