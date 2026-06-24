1. git clone <repo-url>
2. cd <folder>
3. composer install
4. cp .env.example .env
5. php artisan key:generate
6. Edit .env (DB_CONNECTION, DB_DATABASE, dll)
7. php artisan migrate
8. php artisan db:seed        ← kalo mau data kosong. kalo gak mau, silakan inject db lewat php artisan tinker. ga ada fitur registrasi sama sekali
9. npm install
10. npm run build
11. php artisan storage:link   # kalau ada upload file
