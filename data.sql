-- Delete all data in the correct order to respect foreign key relationships

-- First delete from tables with foreign keys that reference other tables
-- Delete from cart_items
DELETE FROM cart_items;

-- Delete from order_items
DELETE FROM order_items;

-- Delete from orders
DELETE FROM orders;

-- Delete from authentication tables
DELETE FROM customer_auth;
DELETE FROM staff_auth;

-- Delete from core tables
DELETE FROM customers;
DELETE FROM staffs;
DELETE FROM products;
DELETE FROM genres;

-- Insert genre data
INSERT INTO genres (genre_id, genre_name) VALUES 
(1, 'Hip Hop'),
(2, 'R&B'),
(3, 'Alternative Hip Hop'),
(4, 'Jazz Rap'),
(5, 'Rock');;

-- Insert products (albums) with random quantities
-- Tyler, The Creator albums
INSERT INTO products (product_id, product_name, genre_id, list_price, album_cover_url, quantity) VALUES 
(1, 'Igor', 3, 2499, 'https://upload.wikimedia.org/wikipedia/en/5/51/Igor_-_Tyler%2C_the_Creator.jpg', 45),
(2, 'Flower Boy', 3, 1999, 'https://upload.wikimedia.org/wikipedia/en/c/c3/Tyler%2C_the_Creator_-_Flower_Boy.png', 72),
(3, 'Call Me If You Get Lost', 1, 2499, 'https://upload.wikimedia.org/wikipedia/en/d/d3/Call_Me_If_You_Get_Lost_album_cover.jpg', 19),
(4, 'Chromakopia', 3, 1799, 'https://t2.genius.com/unsafe/300x0/https%3A%2F%2Fimages.genius.com%2F206f16145c6ad42142656b0a53a0638f.300x300x1.png', 88);

-- Kendrick Lamar albums
INSERT INTO products (product_id, product_name, genre_id, list_price, album_cover_url, quantity) VALUES 
(21, 'To Pimp a Butterfly', 1, 2499, 'https://upload.wikimedia.org/wikipedia/en/f/f6/Kendrick_Lamar_-_To_Pimp_a_Butterfly.png', 63),
(22, 'good kid, m.A.A.d city', 1, 1999, 'https://upload.wikimedia.org/wikipedia/en/9/93/KendrickGKMC.jpg', 27),
(23, 'DAMN.', 1, 2299, 'https://upload.wikimedia.org/wikipedia/en/5/51/Kendrick_Lamar_-_Damn.png', 91),
(24, 'Mr. Morale & the Big Steppers', 1, 2499, 'https://t2.genius.com/unsafe/516x516/https%3A%2F%2Fimages.genius.com%2F2f8cae9b56ed9c643520ef2fd62cd378.1000x1000x1.png', 34);

-- Mac Miller albums
INSERT INTO products (product_id, product_name, genre_id, list_price, album_cover_url, quantity) VALUES 
(31, 'Swimming', 3, 1999, 'https://upload.wikimedia.org/wikipedia/en/5/5e/Mac_Miller_-_Swimming.png', 56),
(32, 'Circles', 3, 2199, 'https://upload.wikimedia.org/wikipedia/en/1/15/Mac_Miller_-_Circles.png', 12),
(33, 'Ballonerism', 2, 1899, 'https://t2.genius.com/unsafe/516x516/https%3A%2F%2Fimages.genius.com%2F8d7b193f970b3ea78bbc42066052d108.569x569x1.png', 78);

-- Childish Gambino albums
INSERT INTO products (product_id, product_name, genre_id, list_price, album_cover_url, quantity) VALUES 
(41, 'Awaken, My Love!', 2, 2199, 'https://upload.wikimedia.org/wikipedia/en/1/10/Childish_Gambino_-_Awaken%2C_My_Love%21.png', 41),
(42, 'Because the Internet', 3, 1999, 'https://t2.genius.com/unsafe/516x516/https%3A%2F%2Fimages.genius.com%2Fa1fe02a2f777b8206d4863294396ce23.1000x1000x1.jpg', 95),
(43, 'Bando Stone and The New World', 3, 2299, 'https://t2.genius.com/unsafe/516x516/https%3A%2F%2Fimages.genius.com%2Ff19320aae82a75396d97def01ae89ff3.1000x1000x1.png', 23);

-- Sample staff data
INSERT INTO staffs (staff_id, staff_name, email, phone) VALUES 
(1, 'John Doe', 'john@mydonut.com', '555-123-4567'),
(2, 'Jane Smith', 'jane@mydonut.com', '555-987-6543'),
(3, 'Admin User', 'admin@mydonut.com', '555-000-0000');

-- Sample customer data
INSERT INTO customers (customer_id, customer_name, phone, email) VALUES 
(1, 'Alex Johnson', '555-111-2222', 'alex@example.com'),
(2, 'Taylor Williams', '555-333-4444', 'taylor@example.com'),
(3, 'Jordan Brown', '555-555-6666', 'jordan@example.com'),
(4, 'hi', '555-111-2222', 'alex@example.com');

-- Sample customer authentication data (password_hash would be properly hashed in production)
INSERT INTO customer_auth (customer_id, username, password_hash, last_login) VALUES 
(1, 'alexj', 'hashed_password_1', '2025-05-30 14:25:10'),
(2, 'taylorw', 'hashed_password_2', '2025-06-01 09:12:30'),
(3, 'jordanb', 'hashed_password_3', '2025-05-28 17:45:22'),
(4, 'hi', '123', '2025-05-30 14:25:10');

-- Sample staff authentication data
INSERT INTO staff_auth (staff_id, username, password_hash, last_login, role) VALUES 
(1, 'johnd', 'hashed_password_staff_1', '2025-06-02 08:30:15', 'manager'),
(2, 'janes', 'hashed_password_staff_2', '2025-06-02 09:15:45', 'sales'),
(3, 'admin', '123', GETDATE(), 'admin');

-- Sample orders
INSERT INTO orders (order_id, customer_id, order_date, staff_id) VALUES 
(1001, 1, '2025-05-15', 2),
(1002, 3, '2025-05-20', 1),
(1003, 2, '2025-05-28', 2);

-- Sample order items
INSERT INTO order_items (order_id, item_id, product_id, quantity) VALUES 
(1001, 1, 2, 1),  -- Flower Boy
(1001, 2, 21, 1),  -- To Pimp a Butterfly
(1002, 1, 31, 2),  -- Swimming (2 copies)
(1002, 2, 42, 1),  -- Because the Internet
(1003, 1, 3, 1),  -- Call Me If You Get Lost
(1003, 2, 23, 1);  -- DAMN.

-- Sample cart items
INSERT INTO cart_items (customer_id, item_id, product_id, quantity, added_at) VALUES 
(4, 2001, 1, 1, '2025-06-01 10:24:30');  -- IGOR