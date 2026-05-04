@echo off
echo.
echo ============================================
echo   REFERRAL BUNNY — PRODUCTION DEPLOY
echo   Target: https://referralbunny.ai
echo ============================================
echo.

cd /d "C:\Users\paulg\OneDrive\Desktop\Referral Bunny API"

echo [1/3] Committing and pushing to GitHub...
git add .
git diff --cached --quiet && echo No changes to commit. || git commit -m "Deploy %date% %time%"
git push origin main

echo.
echo [2/3] Deploying to production server...
ssh root@103.3.62.77 "cd /var/www/referral-bunny && git pull origin main && composer install --no-dev --optimize-autoloader --no-interaction --no-progress && npm ci && npm run build && php artisan config:cache && php artisan route:cache && php artisan view:cache && chown -R www-data:www-data storage bootstrap/cache && echo DONE"

echo.
echo [3/3] Done!
echo Site is live at: https://referralbunny.ai
echo.
pause
