CREATE TABLE IF NOT EXISTS trade_events (
  id INT AUTO_INCREMENT PRIMARY KEY,
  offer_id INT NOT NULL,
  actor_id INT DEFAULT NULL,
  event_type VARCHAR(64) NOT NULL,
  metadata TEXT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_trade_events_offer FOREIGN KEY (offer_id) REFERENCES trade_offers(id) ON DELETE CASCADE,
  CONSTRAINT fk_trade_events_actor FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX IF NOT EXISTS idx_trade_events_offer ON trade_events (offer_id);
CREATE INDEX IF NOT EXISTS idx_trade_events_type ON trade_events (event_type);
