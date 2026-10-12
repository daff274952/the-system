-- Database aplikasi The System.
-- Tidak wajib dijalankan manual: aplikasi membuat database & tabel ini otomatis saat pertama dibuka.

CREATE DATABASE IF NOT EXISTS system_hunter CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE system_hunter;

CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username      VARCHAR(30)  NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  last_login_at DATETIME     NULL
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS hunters (
  user_id      INT UNSIGNED PRIMARY KEY,
  name         VARCHAR(30)  NOT NULL,
  level        INT UNSIGNED NOT NULL DEFAULT 1,
  xp           INT UNSIGNED NOT NULL DEFAULT 0,
  points       INT UNSIGNED NOT NULL DEFAULT 0,
  streak       INT UNSIGNED NOT NULL DEFAULT 0,
  hp           INT UNSIGNED NOT NULL DEFAULT 100,
  mp           INT UNSIGNED NOT NULL DEFAULT 10,
  str          INT UNSIGNED NOT NULL DEFAULT 10,
  agi          INT UNSIGNED NOT NULL DEFAULT 10,
  vit          INT UNSIGNED NOT NULL DEFAULT 10,
  intel        INT UNSIGNED NOT NULL DEFAULT 10,
  per          INT UNSIGNED NOT NULL DEFAULT 10,
  reminders_on TINYINT(1)   NOT NULL DEFAULT 1,
  fired_date   DATE         NULL,
  fired_list   VARCHAR(255) NOT NULL DEFAULT '',
  updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Satu baris per hari = riwayat daily quest.
CREATE TABLE IF NOT EXISTS daily_quests (
  id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id      INT UNSIGNED NOT NULL,
  quest_date   DATE         NOT NULL,
  pushup       SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  situp        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  squat        SMALLINT UNSIGNED NOT NULL DEFAULT 0,
  run_km       DECIMAL(4,1) NOT NULL DEFAULT 0,
  done         TINYINT(1)   NOT NULL DEFAULT 0,
  completed_at DATETIME     NULL,
  UNIQUE KEY uniq_user_date (user_id, quest_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS side_quests (
  id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id    INT UNSIGNED NOT NULL,
  client_id  BIGINT UNSIGNED NOT NULL,
  title      VARCHAR(80)  NOT NULL,
  tier       ENUM('E','D','C','B','A','S') NOT NULL,
  stat       ENUM('str','agi','vit','int','per') NOT NULL,
  done       TINYINT(1)   NOT NULL DEFAULT 0,
  UNIQUE KEY uniq_user_client (user_id, client_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS reminder_times (
  user_id     INT UNSIGNED NOT NULL,
  remind_time CHAR(5)      NOT NULL,
  PRIMARY KEY (user_id, remind_time),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS system_logs (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id   INT UNSIGNED NOT NULL,
  logged_at BIGINT UNSIGNED NOT NULL,
  message   VARCHAR(255) NOT NULL,
  KEY idx_user_time (user_id, logged_at),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Profil fisik & target Player, diisi saat pertama daftar.
CREATE TABLE IF NOT EXISTS profiles (
  user_id           INT UNSIGNED PRIMARY KEY,
  gender            ENUM('L','P') NULL,
  birth_date        DATE          NULL,
  height_cm         SMALLINT UNSIGNED NOT NULL,
  weight_kg         DECIMAL(5,1)  NOT NULL,
  target_weight_kg  DECIMAL(5,1)  NULL,
  target_rank       ENUM('E','D','C','B','A','S') NOT NULL DEFAULT 'C',
  fitness_level     ENUM('pemula','menengah','lanjut') NOT NULL DEFAULT 'pemula',
  main_goal         ENUM('turun_berat','bangun_otot','stamina','disiplin') NOT NULL DEFAULT 'disiplin',
  target_push       SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  target_sit        SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  target_squat      SMALLINT UNSIGNED NOT NULL DEFAULT 20,
  target_run_km     DECIMAL(4,1)  NOT NULL DEFAULT 2,
  created_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Riwayat berat badan, satu baris per hari.
CREATE TABLE IF NOT EXISTS weight_logs (
  user_id    INT UNSIGNED NOT NULL,
  log_date   DATE         NOT NULL,
  weight_kg  DECIMAL(5,1) NOT NULL,
  PRIMARY KEY (user_id, log_date),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Sesi login disimpan di database (bukan file), supaya tetap jalan di serverless seperti Vercel.
CREATE TABLE IF NOT EXISTS sessions (
  id         VARCHAR(128) PRIMARY KEY,
  data       MEDIUMBLOB   NOT NULL,
  expires_at INT UNSIGNED NOT NULL,
  KEY idx_expires (expires_at)
) ENGINE=InnoDB;

-- Catatan internal aplikasi, misalnya versi schema yang sudah dijalankan.
CREATE TABLE IF NOT EXISTS meta (
  k VARCHAR(50)  PRIMARY KEY,
  v VARCHAR(100) NOT NULL
) ENGINE=InnoDB;
