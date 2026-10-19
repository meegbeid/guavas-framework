-- Schema layanan registrasi Guavas pada database db_meegbe (server MeeGbe).
-- Key registrasi dibuat dengan CSPRNG, ditampilkan sekali, lalu hanya SHA-256
-- dari bentuk kanonisnya yang disimpan sebagai BINARY(32).
-- File ini mendefinisikan tabel baru; penghapusan data lama dilakukan terpisah.

CREATE TABLE `tbl_licenses` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `customer_id` int(11) unsigned DEFAULT NULL COMMENT 'Akun tbl_customers bila tersedia',
  `customer_name` varchar(150) NOT NULL,
  `key_prefix` char(8) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `key_hash` binary(32) NOT NULL COMMENT 'SHA-256 key registrasi kanonis',
  `status` varchar(16) NOT NULL DEFAULT 'active' COMMENT 'active atau revoked',
  `added` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_license_key_hash` (`key_hash`),
  KEY `idx_license_customer` (`customer_id`),
  CONSTRAINT `fk_license_customer` FOREIGN KEY (`customer_id`) REFERENCES `tbl_customers` (`customer_id`) ON DELETE SET NULL,
  CONSTRAINT `chk_license_status` CHECK (`status` IN ('active', 'revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_license_installations` (
  `id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL COMMENT 'UUID acak per instalasi',
  `license_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `project_label` varchar(150) DEFAULT NULL,
  `site_url` varchar(255) DEFAULT NULL,
  `framework_version` varchar(32) DEFAULT NULL,
  `token_hash` binary(32) DEFAULT NULL COMMENT 'SHA-256 token instalasi; tidak menyimpan token asli',
  `status` varchar(16) NOT NULL DEFAULT 'active' COMMENT 'active atau revoked',
  `last_ip` varchar(45) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL,
  `first_seen` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_seen` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_installation_token_hash` (`token_hash`),
  KEY `idx_installation_license` (`license_id`),
  CONSTRAINT `fk_installation_license` FOREIGN KEY (`license_id`) REFERENCES `tbl_licenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_installation_status` CHECK (`status` IN ('active', 'revoked'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_license_update_entitlements` (
  `license_id` char(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` varchar(16) NOT NULL DEFAULT 'active' COMMENT 'active atau suspended',
  `updates_until` datetime DEFAULT NULL COMMENT 'NULL berarti tanpa batas waktu selama status active',
  `added` timestamp NOT NULL DEFAULT current_timestamp(),
  `modified` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`license_id`),
  CONSTRAINT `fk_update_entitlement_license` FOREIGN KEY (`license_id`) REFERENCES `tbl_licenses` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_update_entitlement_status` CHECK (`status` IN ('active', 'suspended'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `tbl_license_api_limits` (
  `ip_hash` binary(32) NOT NULL,
  `attempts` smallint unsigned NOT NULL DEFAULT 0,
  `window_start` datetime NOT NULL,
  PRIMARY KEY (`ip_hash`),
  KEY `idx_license_api_limit_window` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
