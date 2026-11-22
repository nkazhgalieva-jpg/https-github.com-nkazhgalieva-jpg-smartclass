# SENDE Smart Class — этап 1 (без базы данных)
Данные хранятся в JSON-файлах (storage/data), загрузки — в storage/uploads. SQL и MySQL не нужны.
Требования: PHP 8.2 (fileinfo, mbstring), Apache или nginx с PHP-FPM. Подходит для одной школы (сотни учеников).

1. Document root веб-сервера = папка public/. Папка storage/ НЕ должна отдаваться по HTTP (в Apache закрыта .htaccess; в nginx добавьте deny).
2. У PHP должны быть права на запись в storage/data и storage/uploads.
3. HTTPS обязателен. В php.ini: file_uploads=On, upload_max_filesize=10M, post_max_size=50M.
4. Создайте учителя: php scripts/create_user.php teacher "ФИО" email@school.kz 'пароль-мин-8'
5. Ученики регистрируются на /register.php и вводят код класса.
6. Резервная копия = копия папки storage/.
Быстрый локальный запуск: php -S localhost:8000 -t public
