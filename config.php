<?php
// Базы данных нет: все данные хранятся в JSON-файлах в storage/data (вне публичной папки).
return [
  'data_dir' => getenv('SC_DATA_DIR') ?: __DIR__ . '/storage/data',
  'upload_dir' => __DIR__ . '/storage/uploads',
  'max_file_bytes' => 10 * 1024 * 1024,
  'max_files' => 5,
  // Ключ Gemini (этап с AI) — только из переменной окружения, никогда в браузер
  'gemini_key' => getenv('SC_GEMINI_KEY') ?: '',
];
