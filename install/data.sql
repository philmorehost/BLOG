
INSERT IGNORE INTO categories (name, slug) VALUES
('Football News', 'football-news'), ('Transfer News', 'transfer-news');

INSERT IGNORE INTO site_settings (id, name, tagline, selected_model) VALUES
(1, 'BLOGEASY', 'General News & Intelligence Network', 'deepseek-chat');

INSERT IGNORE INTO pages (title, slug, content, is_visible, position) VALUES
('Privacy Policy', 'privacy-policy', '# Privacy Policy\n\nYour privacy is important to us. This privacy policy explains how we collect, use, and protect your personal information when you use our website.\n\n## 1. Information We Collect\nWe may collect personal information such as your name, email address, and IP address when you interact with our site.\n\n## 2. How We Use Your Information\nWe use your information to provide and improve our services, communicate with you, and ensure the security of our network.\n\n## 3. Cookies\nWe use cookies to enhance your experience and analyze our traffic.\n\n## 4. Security\nWe implement robust security measures to protect your data from unauthorized access.\n\n## 5. Contact Us\nIf you have any questions about this policy, please contact us.', 1, 'main'),
('About Us', 'about-us', '# About Football Intelligence Network\n\nWelcome to the most advanced football intelligence hub.\n\n## Our Mission\nOur mission is to provide real-time, professional-grade football intelligence and transfer updates to fans globally. We leverage expert insights to bring you the stories that matter.\n\n## The Team\nOur team consists of veteran sports journalists and data analysts dedicated to 100 percent human-verified reporting.', 1, 'footer');
