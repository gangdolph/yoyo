-- SQL schema reference for listing change requests
CREATE TABLE listing_change_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    requester_id INT NOT NULL,
    reviewer_id INT DEFAULT NULL,
    change_type VARCHAR(32) NOT NULL DEFAULT 'status',
    change_summary TEXT DEFAULT NULL,
    requested_status ENUM('draft','pending','approved','live','closed','delisted') DEFAULT NULL,
    payload JSON DEFAULT NULL,
    status ENUM('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
    review_notes TEXT DEFAULT NULL,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (listing_id) REFERENCES listings(id) ON DELETE CASCADE,
    FOREIGN KEY (requester_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_listing_change_requests_listing_status ON listing_change_requests (listing_id, status);
CREATE INDEX idx_listing_change_requests_requester ON listing_change_requests (requester_id);
CREATE INDEX idx_listing_change_requests_reviewer ON listing_change_requests (reviewer_id);
