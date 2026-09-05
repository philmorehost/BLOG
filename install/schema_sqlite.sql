
CREATE TABLE IF NOT EXISTS site_settings (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
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
    taxonomy_migrated BOOLEAN DEFAULT FALSE
);

CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    role VARCHAR(20) DEFAULT 'admin',
    reset_token VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS posts (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    excerpt TEXT,
    content TEXT,
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
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    post_id INT NOT NULL,
    author VARCHAR(100) NOT NULL,
    text TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (post_id) REFERENCES posts(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS subscribers (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS pages (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content TEXT,
    is_external BOOLEAN DEFAULT FALSE,
    external_url VARCHAR(255),
    is_visible BOOLEAN DEFAULT TRUE,
    position TEXT DEFAULT 'main',
    meta_title VARCHAR(255),
    meta_description TEXT,
    meta_keywords TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name VARCHAR(100) UNIQUE NOT NULL,
    slug VARCHAR(100)
);

INSERT INTO site_settings (name, tagline, selected_model) VALUES ('FOOTBALL INTELLIGENCE', 'Sports Intelligence Network', 'deepseek-chat');
INSERT INTO categories (name, slug) VALUES ('Football News', 'football-news'), ('Transfer News', 'transfer-news');
INSERT INTO pages (title, slug, content, is_visible, position) VALUES ('Privacy Policy', 'privacy-policy', '# Privacy Policy\n\nYour privacy is important to us.', 1, 'main');
INSERT INTO pages (title, slug, content, is_visible, position) VALUES ('About Us', 'about-us', '# About Football Intelligence Network\n\nWelcome to the most advanced football intelligence hub.\n\n## Our Mission\nOur mission is to provide real-time, professional-grade football intelligence and transfer updates to fans globally. We leverage expert insights to bring you the stories that matter.\n\n## The Team\nOur team consists of veteran sports journalists and data analysts dedicated to 100 percent human-verified reporting.', 1, 'footer');
