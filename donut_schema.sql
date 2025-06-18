CREATE TABLE genres (
    genre_id INT,
    genre_name VARCHAR(255),
    PRIMARY KEY (genre_id)
);

CREATE TABLE products (
    product_id INT,
    product_name VARCHAR(255),
    genre_id INT,
    list_price INT,
    album_cover_url VARCHAR(500),
    quantity INT NOT NULL DEFAULT 0,
    PRIMARY KEY (product_id),
    FOREIGN KEY (genre_id) REFERENCES genres(genre_id)
);

CREATE TABLE staffs (
    staff_id INT,
    staff_name VARCHAR(255),
    email VARCHAR(255),
    phone VARCHAR(25),
    PRIMARY KEY (staff_id)
);

CREATE TABLE customers (
    customer_id INT,
    customer_name VARCHAR(255),
    phone VARCHAR(25),
    email VARCHAR(255),
    PRIMARY KEY (customer_id)
);

CREATE TABLE orders (
    order_id INT,
    customer_id INT,
    order_date DATE,
    staff_id INT,
    PRIMARY KEY (order_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (staff_id) REFERENCES staffs(staff_id)
);

CREATE TABLE order_items (
    order_id INT,
    item_id INT,
    product_id INT,
    quantity INT,
    PRIMARY KEY (order_id, item_id),
    FOREIGN KEY (order_id) REFERENCES orders(order_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

-- Add login credentials for customers
CREATE TABLE customer_auth (
    customer_id INT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login DATETIME,
    PRIMARY KEY (customer_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id)
);

-- Add login credentials for staff
CREATE TABLE staff_auth (
    staff_id INT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    last_login DATETIME,
    role VARCHAR(50) NOT NULL, -- e.g., 'admin', 'sales', 'manager'
    PRIMARY KEY (staff_id),
    FOREIGN KEY (staff_id) REFERENCES staffs(staff_id)
);

-- Shopping cart items
CREATE TABLE cart_items (
    customer_id INT,
    item_id INT,
    product_id INT,
    quantity INT NOT NULL DEFAULT 1,
    added_at DATETIME DEFAULT GETDATE(),
    PRIMARY KEY (customer_id, item_id),
    FOREIGN KEY (customer_id) REFERENCES customers(customer_id),
    FOREIGN KEY (product_id) REFERENCES products(product_id)
);

