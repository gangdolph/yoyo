ALTER TABLE forum_posts
  ADD COLUMN parent_id INT DEFAULT NULL,
  ADD CONSTRAINT fk_forum_posts_parent
    FOREIGN KEY (parent_id) REFERENCES forum_posts(id)
    ON DELETE CASCADE;
