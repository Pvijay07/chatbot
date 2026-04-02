CREATE TABLE IF NOT EXISTS chats (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
    title VARCHAR(150) NOT NULL,
    pet_type VARCHAR(20) NULL,
    last_message_at DATETIME NULL,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_chats_user_id (user_id)
);

CREATE TABLE IF NOT EXISTS messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chat_id BIGINT UNSIGNED NOT NULL,
    sender VARCHAR(20) NOT NULL,
    message LONGTEXT NOT NULL,
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    sources_json LONGTEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_messages_chat_id (chat_id),
    CONSTRAINT fk_messages_chat FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE TABLE IF NOT EXISTS insurance_plans (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    pet_type VARCHAR(20) NOT NULL,
    slug VARCHAR(80) NOT NULL UNIQUE,
    name_en VARCHAR(120) NOT NULL,
    name_hi VARCHAR(160) NOT NULL,
    summary_en TEXT NOT NULL,
    summary_hi TEXT NOT NULL,
    price_monthly DECIMAL(10,2) NOT NULL,
    annual_limit INT UNSIGNED NOT NULL,
    deductible INT UNSIGNED NOT NULL,
    reimbursement_percent INT UNSIGNED NOT NULL,
    waiting_period_days INT UNSIGNED NOT NULL,
    claim_steps_en TEXT NOT NULL,
    claim_steps_hi TEXT NOT NULL,
    exclusions_en TEXT NOT NULL,
    exclusions_hi TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_insurance_plans_pet_type (pet_type)
);

CREATE TABLE IF NOT EXISTS insurance_documents (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    file_name VARCHAR(255) NULL,
    file_path VARCHAR(255) NULL,
    mime_type VARCHAR(120) NULL,
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    content_hash VARCHAR(64) NULL,
    uploaded_by BIGINT UNSIGNED NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NULL,
    updated_at DATETIME NULL,
    KEY idx_insurance_documents_language (language)
);

CREATE TABLE IF NOT EXISTS insurance_document_chunks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    document_id BIGINT UNSIGNED NOT NULL,
    chunk_index INT UNSIGNED NOT NULL,
    language VARCHAR(5) NOT NULL DEFAULT 'en',
    content LONGTEXT NOT NULL,
    token_count INT UNSIGNED NOT NULL DEFAULT 0,
    keywords TEXT NULL,
    created_at DATETIME NOT NULL,
    KEY idx_insurance_document_chunks_document_id (document_id),
    KEY idx_insurance_document_chunks_language (language),
    CONSTRAINT fk_chunks_document FOREIGN KEY (document_id) REFERENCES insurance_documents(id) ON DELETE CASCADE ON UPDATE CASCADE
);
