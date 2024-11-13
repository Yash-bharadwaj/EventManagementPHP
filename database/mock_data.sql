-- File: database/mock_data.sql

-- Insert Categories
INSERT INTO categories (name, description) VALUES
('Music', 'Live concerts, festivals, and musical performances'),
('Sports', 'Sports events, tournaments, and matches'),
('Technology', 'Tech conferences, workshops, and meetups'),
('Arts', 'Art exhibitions, theater shows, and cultural events'),
('Food', 'Food festivals, cooking workshops, and culinary events');

-- Insert Sample Events
INSERT INTO events (
    title, 
    description, 
    category_id,
    start_date,
    end_date,
    location,
    capacity,
    price,
    status,
    created_by
) VALUES
-- Music Events
('Summer Music Festival 2024', 
 'Join us for an incredible weekend of live music featuring top artists from around the world.',
 1, 
 DATE_ADD(CURRENT_DATE, INTERVAL 30 DAY),
 DATE_ADD(CURRENT_DATE, INTERVAL 32 DAY),
 'Central Park Amphitheater',
 5000,
 99.99,
 'published',
 1),

('Jazz Night Under the Stars', 
 'An elegant evening of jazz music with renowned performers.',
 1,
 DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY),
 DATE_ADD(CURRENT_DATE, INTERVAL 15 DAY),
 'Skyline Rooftop Lounge',
 200,
 75.00,
 'published',
 1),

-- Sports Events
('Championship Finals 2024',
 'The ultimate showdown for the championship title.',
 2,
 DATE_ADD(CURRENT_DATE, INTERVAL 45 DAY),
 DATE_ADD(CURRENT_DATE, INTERVAL 45 DAY),
 'City Stadium',
 10000,
 149.99,
 'published',
 1),

-- Tech Events
('Tech Innovation Summit',
 'Explore the latest innovations in technology with industry leaders.',
 3,
 DATE_ADD(CURRENT_DATE, INTERVAL 20 DAY),
 DATE_ADD(CURRENT_DATE, INTERVAL 22 DAY),
 'Convention Center',
 1000,
 299.99,
 'published',
 1),

-- Art Events
('Modern Art Exhibition',
 'Featuring contemporary artworks from emerging artists.',
 4,
 DATE_ADD(CURRENT_DATE, INTERVAL 10 DAY),
 DATE_ADD(CURRENT_DATE, INTERVAL 25 DAY),
 'Downtown Art Gallery',
 300,
 25.00,
 'published',
 1);

-- Insert some sample bookings
INSERT INTO bookings (
    event_id,
    user_id,
    quantity,
    total_amount,
    status,
    payment_status
) 
SELECT 
    id as event_id,
    1 as user_id,
    FLOOR(RAND() * 5) + 1 as quantity,
    price * (FLOOR(RAND() * 5) + 1) as total_amount,
    'confirmed' as status,
    'completed' as payment_status
FROM events;