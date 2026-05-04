@echo off
echo.
echo ============================================
echo   REFERRAL BUNNY — LOCAL DEPLOY (Staging)
echo   Target: http://103.3.62.77:8080
echo   Visible to: You only
echo ============================================
echo.

cd /d "C:\Users\paulg\OneDrive\Desktop\Referral Bunny API"

echo [1/2] Pushing code to GitHub (staging branch)...
git add .
git diff --cached --quiet && echo No changes to commit. || git commit -m "Staging %date% %time%"
git push origin main

echo.
echo [2/2] Deploying to staging server...
ssh root@103.3.62.77 "cd /var/www/referral-bunny-staging && git pull origin main && composer install --no-dev --optimize-autoloader --no-interaction --no-progress && npm ci && npm run build && php artisan config:cache && php artisan route:cache && php artisan view:cache && chown -R www-data:www-data storage bootstrap/cache && echo DONE"

echo.
echo Staging is live at: http://103.3.62.77:8080
echo (Only visible to you)
echo.
pause
