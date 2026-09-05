
CREATE TABLE IF NOT EXISTS site_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    tagline VARCHAR(255),
    logo VARCHAR(255),
    admin_email VARCHAR(255),
    whatsapp_number VARCHAR(50),
    header_code TEXT,
    footer_code TEXT,
    selected_model VARCHAR(50) DEFAULT 'deepseek-chat',
    deepseek_api_key VARCHAR(255),
    news_api_key VARCHAR(255),
    smtp_host VARCHAR(255),
    smtp_port VARCHAR(10),
    smtp_user VARCHAR(255),
    smtp_pass VARCHAR(255),
    smtp_sender_email VARCHAR(255),
    smtp_sender_name VARCHAR(255),
    fb_url VARCHAR(255),
    tw_url VARCHAR(255),
    ig_url VARCHAR(255),
    yt_url VARCHAR(255),
    pin_enabled BOOLEAN DEFAULT FALSE,
    admin_pin VARCHAR(255),
    favicon VARCHAR(255),
    fb_app_id VARCHAR(255),
    fb_app_secret VARCHAR(255),
    fb_page_id VARCHAR(255),
    fb_access_token TEXT,
    tw_client_id VARCHAR(255),
    tw_client_secret VARCHAR(255),
    tw_api_key VARCHAR(255),
    tw_api_secret VARCHAR(255),
    tw_access_token VARCHAR(255),
    tw_access_secret VARCHAR(255),
    ig_account_id VARCHAR(255),
    ig_access_token TEXT,
    tt_client_key VARCHAR(255),
    tt_client_secret VARCHAR(255),
    tt_access_token TEXT,
    taxonomy_migrated BOOLEAN DEFAULT FALSE,
    theme VARCHAR(50) DEFAULT 'news',
    section_priority_title VARCHAR(255) DEFAULT 'Priority Intelligence',
    section_latest_title VARCHAR(255) DEFAULT 'Latest Intelligence',
    section_football_title VARCHAR(255) DEFAULT 'Football News',
    section_transfer_title VARCHAR(255) DEFAULT 'Transfer Intelligence'
);

CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    reset_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT,
    content LONGTEXT,
    category VARCHAR(100),
    author VARCHAR(100),
    image VARCHAR(255),
    video_url VARCHAR(255),
    source_url VARCHAR(255),
    is_top_story BOOLEAN DEFAULT FALSE,
    is_scheduled BOOLEAN DEFAULT FALSE,
    publish_date DATETIME,
    tags TEXT,
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS comments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    post_id INT NOT NULL,
    author VARCHAR(100) NOT NULL,
    text TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'spam') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS subscribers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    is_external BOOLEAN DEFAULT FALSE,
    external_url VARCHAR(255),
    is_visible BOOLEAN DEFAULT TRUE,
    position ENUM('top', 'main', 'footer') DEFAULT 'main',
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100)
);

CREATE INDEX idx_posts_slug ON posts(slug);
CREATE INDEX idx_posts_category ON posts(category);
CREATE INDEX idx_posts_created_at ON posts(created_at);
CREATE INDEX idx_posts_publish_date ON posts(publish_date);
CREATE INDEX idx_pages_slug ON pages(slug);
CREATE INDEX idx_categories_slug ON categories(slug);
