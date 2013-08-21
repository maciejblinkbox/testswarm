-- creating user_latest table replaces nested query from getRunAction PHP page
-- user_latest lookup table needs to be mainained, so neccessary triggers are introduced
CREATE TABLE user_latest (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

  `user_id` int unsigned NOT NULL,

  `useragent_id` varchar(255) NOT NULL default '',

  `max_updated` binary(14) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;


-- unique index to enforce uniqness of data in lookup table
CREATE UNIQUE INDEX idx_user_latest_user_id_useragent_id ON user_latest (user_id, useragent_id);


-- populate lookup table with initial data
INSERT INTO user_latest
(
  user_id,
  useragent_id,
  max_updated
)
SELECT  
  jobs.user_id, 
  run_useragent.useragent_id,
  MAX(run_useragent.updated) AS MaxUpdated
FROM  users
INNER JOIN jobs ON users.id = jobs.user_id
INNER JOIN runs ON jobs.id = runs.job_id
INNER JOIN run_useragent ON runs.id = run_useragent.run_id
GROUP BY jobs.user_id, run_useragent.useragent_id;


-- create stored procedure for keeping user_latest lookup table up to date
DELIMITER //
CREATE PROCEDURE sp_calculate_user_latest 
(
  IN p_run_id     INT,
  IN p_updated    BINARY(14),
  IN p_useragent_id VARCHAR(255)
)
BEGIN

  -- find user_id for current run
  SELECT  j.user_id
  INTO  @user_id
  FROM  runs r
  JOIN  jobs j ON j.id = r.job_id
  WHERE r.id = p_run_id;

  -- update user_latest, set max_updated for current useragent and user
  -- we are assuming here that the application only sets the updated column to current timestamp
  -- which always should be the highest value
  -- the alternative is to run MAX() function to find the actual highest updated value
  -- decision was made that running MAX() is too resource intensive
  
  SELECT COUNT(*)
  INTO @rowcount
  FROM user_latest
  WHERE     user_id = @user_id
  AND   useragent_id = p_useragent_id;

  IF @rowcount = 0 THEN
    INSERT user_latest ( user_id, useragent_id, max_updated )
    VALUES ( @user_id, p_useragent_id, p_updated );
  ELSE
   UPDATE    user_latest
   SET     max_updated = p_updated
   WHERE     user_id = @user_id
   AND   useragent_id = p_useragent_id;
  END IF;

END //
DELIMITER ;


-- create insert trigger that calls sp_calculate_user_latest stored procedure
DELIMITER //
CREATE TRIGGER trg_insert_updated AFTER INSERT ON run_useragent
FOR EACH ROW 
BEGIN
  CALL sp_calculate_user_latest ( 
    NEW.run_id,
    NEW.updated,
    NEW.useragent_id
  );

END //
DELIMITER ;


-- create update trigger that calls sp_calculate_user_latest stored procedure
DELIMITER //
CREATE TRIGGER trg_update_updated AFTER UPDATE ON run_useragent
FOR EACH ROW 
BEGIN
  CALL sp_calculate_user_latest ( 
    NEW.run_id,
    NEW.updated,
    NEW.useragent_id
  );

END //
DELIMITER ;


-- priority column helps us to prioritize developer jobs
ALTER TABLE Users ADD COLUMN `priority` tinyint unsigned NOT NULL default 1;
UPDATE Users SET priority = 255 where name = 'hudson';


-- the following index improves the execution speed of the outer query from getRunAction PHP page
-- query seems to be instant now.
CREATE INDEX id_run_useragent_status_useragent_id ON run_useragent (status, useragent_id);


DELIMITER //
CREATE PROCEDURE sp_get_next_run_new
(
  IN p_ci_username  VARCHAR(255), -- not needed
  IN p_useragent_id   VARCHAR(255)  
)
BEGIN
  -- this is the new query that uses new user_latest table in subquery and new index in the outer query
  -- execution seems to be instant (15ms)
  SELECT  run_useragent.run_id

  FROM  jobs    
  INNER JOIN runs ON jobs.id = runs.job_id
  INNER JOIN run_useragent  ON runs.id = run_useragent.run_id
  LEFT OUTER JOIN (
    /* This gives us a list of users who have run jobs and when they last updated a run */
    SELECT  ul.user_id,
        ul.max_updated,
        u.priority
    FROM  user_latest ul
    JOIN  users u on u.id = ul.user_id
    WHERE ul.useragent_id = p_useragent_id
  ) nextUser ON nextUser.user_id = jobs.user_id   

  WHERE run_useragent.useragent_id = p_useragent_id
  AND run_useragent.status = 0  -- idle (awaiting (re-)run)
  ORDER BY  nextUser.Priority,
        nextUser.max_updated, 
        nextUser.user_id, 
        run_useragent.run_id DESC
  LIMIT 1;

END//
DELIMITER ;


DELIMITER //
CREATE PROCEDURE sp_get_next_run_old
(
  IN p_ci_username  VARCHAR(255),
  IN p_useragent_id   VARCHAR(255)  
)
BEGIN

  SELECT  run_useragent.run_id
  FROM  jobs    
  INNER JOIN runs ON jobs.id = runs.job_id
  INNER JOIN run_useragent  ON runs.id = run_useragent.run_id
  LEFT OUTER JOIN (
    /* This gives us a list of users who have run jobs and when they last updated a run */
    SELECT  jobs.user_id, 
        MAX(run_useragent.updated) AS MaxUpdated, 
        CASE users.name WHEN p_ci_username THEN 2 ELSE 1 END AS Priority
    FROM  users
        INNER JOIN jobs ON users.id = jobs.user_id
        INNER JOIN runs ON jobs.id = runs.job_id
        INNER JOIN run_useragent ON runs.id = run_useragent.run_id AND run_useragent.useragent_id = p_useragent_id
    GROUP BY jobs.user_id
  ) nextUser ON nextUser.user_id = jobs.user_id   
  WHERE useragent_id = p_useragent_id
  AND run_useragent.status = 0
  ORDER BY  nextUser.Priority,
        nextUser.MaxUpdated, 
        nextUser.user_id, 
        run_useragent.run_id DESC
  LIMIT 1;

END//
DELIMITER ;


DELIMITER //
CREATE PROCEDURE sp_get_next_run
(
  IN p_ci_username  VARCHAR(255),
  IN p_useragent_id   VARCHAR(255)  
)
BEGIN
  
  CALL sp_get_next_run_old (p_ci_username, p_useragent_id);

END//
DELIMITER ;
