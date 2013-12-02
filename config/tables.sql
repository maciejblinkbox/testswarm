-- --------------------------------------------------------

--
-- Table structure for table `projects`
-- Insertions handled by the manageProject.php script.
--

CREATE TABLE `projects` (
  `id` varchar(255) binary NOT NULL PRIMARY KEY,

  -- Human readable display title for the front-end.
  `display_title` varchar(255) binary NOT NULL,

  -- URL pointing to a page with more information about this project
  -- (Optional field, can be empty).
  `site_url` blob,

  -- Project priority to decide which job runs first.
  `priority` int unsigned NOT NULL,

  -- Salted hash of password (see LoginAction::comparePasswords).
  `password` tinyblob NOT NULL,

  -- SHA1 hash of authentication token.
  -- Refresh handled by the refreshProjectToken.php script.
  `auth_token` tinyblob NOT NULL,

  -- Project update timestamp (YYYYMMDDHHMMSS timestamp).
  `updated` binary(14) NOT NULL,

  -- Project creation timestamp (YYYYMMDDHHMMSS timestamp).
  `created` binary(14) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `devices`
-- Insertions and updates handled by the Device class.
--

CREATE TABLE `devices` (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

  -- Unique key to identify device, such as a serial number.
  `device_key` varchar(255) binary NOT NULL UNIQUE,

  -- Freeform device name.
  `name` varchar(255) binary NOT NULL,

  -- Type of device (STB, TV, Dekstop, etc)
  `device_type` varchar(20) NOT NULL default '',

  -- Model often associated with TV Devices (ie. "UE46 ES8000U" on Samsung)
  `model` varchar(100) NOT NULL default ''

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
-- Insertions and updates handled by the Client class.
--

CREATE TABLE `clients` (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

  -- Freeform client name.
  `name` varchar(255) binary NOT NULL,

  -- Key to devices.id field.
  `device_id` int unsigned NULL,

  -- The index of a device. Increments for each new client of a device.
  `device_index` int unsigned NULL,

  -- Key to config.userAgents property.
  `useragent_id` varchar(255) NOT NULL,

  -- Raw User-Agent string.
  `useragent` tinytext NOT NULL,

  -- Raw IP string as extractred by WebRequest::getIP
  `ip` varbinary(40) NOT NULL default '',

  -- JSON of additional client/device details - gzipped.
  `details_json` blob NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `updated` binary(14) NOT NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `created` binary(14) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Usage: HomePage, SwarmstateAction.
CREATE INDEX idx_clients_useragent_updated ON clients (useragent_id, updated);

-- Usage: CleanupAction.
CREATE INDEX idx_clients_updated ON clients (updated);

-- Usage: ClientAction, ScoresAction, BrowserInfo and Client.
CREATE INDEX idx_clients_name_ua_created ON clients (name, useragent_id, created);

-- Usage: DeviceAction
CREATE INDEX idx_clients_deviceid ON clients (device_id);

-- --------------------------------------------------------

--
-- Table structure for table `jobs`
-- Insertions handled by the AddjobAction class.
--

CREATE TABLE `jobs` (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

  -- Job name (can contain HTML).
  `name` varchar(255) binary NOT NULL default '',

  -- Key to projects.id field.
  `project_id` varchar(255) binary NOT NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `created` binary(14) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Usage: ProjectAction.
CREATE INDEX idx_jobs_project_created ON jobs (project_id, created);

-- --------------------------------------------------------

--
-- Table structure for table `job_useragent`
--

CREATE TABLE `job_useragent` (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

-- Key to jobs.id field.
  `job_id` int unsigned NOT NULL default 0,

-- Key to config.userAgents property.
  `useragent_id` varchar(255) NOT NULL default '',

  -- Job useragent status
  `calculated_summary` blob NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_job_useragent_job_useragent ON job_useragent (job_id, useragent_id);

-- --------------------------------------------------------

--
-- Table structure for table `runs`
-- Insertions handled by the AddjobAction class.
--

CREATE TABLE `runs` (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

  -- Key to jobs.id field.
  `job_id` int unsigned NOT NULL,

  -- Run name
  `name` varchar(255) binary NOT NULL default '',

  -- Run url
  `url` tinytext NOT NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `created` binary(14) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Usage: JobAction.
CREATE INDEX idx_runs_jobid ON runs (job_id);

-- --------------------------------------------------------

--
-- Table structure for table `run_useragent`
-- Insertions handled by the AddjobAction class. Updates by SaverunAction,
-- WipejobAction, and WiperunAction.
--

CREATE TABLE `run_useragent` (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

  -- Key to runs.id field.
  `run_id` int unsigned NOT NULL default 0,

  -- Key to config.userAgents property.
  `useragent_id` varchar(255) NOT NULL default '',

  -- Addjob runMax
  `max` int unsigned NOT NULL default 1,

  -- Number of times this run has run to completion for this user agent.
  -- In most cases this will end up being set to 1 once and then never
  -- touched again. If a client completes a run with one or more failed
  -- unit tests, another client will get the same run to phase out / reduce the
  -- risk of false negatives due to coindicing browser/connectivity issues,
  -- until `max` is reached.
  `completed` int unsigned NOT NULL default 0,

  -- Run status
  -- 0 = idle (awaiting (re-)run)
  -- 1 = busy (being run by a client)
  -- 2 = done (passed and/or reached max)
  -- 3 = suspended (suspended by user)
  `status` tinyint unsigned NOT NULL default 0,

  -- Key to runresults.id field.
  -- If NULL, it means this run has not been ran yet (or it was wiped / cleaned).
  `results_id` int unsigned default NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `updated` binary(14) NOT NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `created` binary(14) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

CREATE UNIQUE INDEX idx_run_useragent_run_useragent ON run_useragent (run_id, useragent_id);

-- Usage: GetrunAction.
CREATE INDEX idx_run_useragent_useragent_status_run ON run_useragent (useragent_id, status, run_id);

-- Usage: CleanupAction.
CREATE INDEX ids_runs_results ON run_useragent (results_id);

-- --------------------------------------------------------

--
-- Table structure for table `runresults`
-- Insertions handled by the GetrunAction class. Updates by SaverunAction.
-- Should never be removed from.

CREATE TABLE `runresults` (
  `id` int unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT,

  -- Key to runs.id field.
  `run_id` int unsigned NOT NULL,

  -- Key to clients.id field.
  `client_id` int unsigned NOT NULL,

  -- Client run status
  -- 1 = busy
  -- 2 = finished
  -- 3 = timed-out (maximum execution time exceeded)
  -- 4 = timed-out (client lost, set from CleanupAction)
  -- 5 = heartbeat
  `status` tinyint unsigned NOT NULL default 0,

  -- Total number of tests ran.
  `total` int unsigned NOT NULL default 0,

  -- Number of failed tests.
  `fail` int unsigned NOT NULL default 0,

  -- Number of errors.
  `error` int unsigned NOT NULL default 0,

  -- HTML snapshot of the test results page - gzipped.
  `report_html` blob NULL,

  -- JSON snapshot of the test results page - gzipped.
  `report_json` blob NULL,

  -- Hash of random-generated token. To use as authentication to be allowed to
  -- store runresults in this row. This protects SaverunAction from bad
  -- insertions (otherwise the only ID is the auto incrementing ID, which is
  -- easy to fake).
  `store_token` binary(40) NOT NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `updated` binary(14) NOT NULL,

  -- YYYYMMDDHHMMSS timestamp. update is expected to be before this timestamp.
  `next_heartbeat` binary(14) NULL,

  -- YYYYMMDDHHMMSS timestamp.
  `created` binary(14) NOT NULL

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Usage: DevicesAction.
CREATE INDEX idx_runresults_client ON runresults (client_id);

-- Usage: GetrunAction.
CREATE INDEX idx_runresults_run_client ON runresults (run_id, client_id);

-- Usage: CleanupAction.
CREATE INDEX idx_runresults_status_client ON runresults (status, client_id);

-- Usage: ScoresAction.
CREATE INDEX idx_runresults_client_total ON runresults (client_id, total);


-- Project lookup table to see the last updated run by project per useragent.
-- This allows us to more efficiently query the next run.

CREATE TABLE `project_updated` (
  -- Key to projects.id field.
  `project_id` varchar(255) binary NOT NULL,

  -- Key to config.userAgents property.
  `useragent_id` varchar(255) NOT NULL default '',

  -- YYYYMMDDHHMMSS timestamp.
  `updated` binary(14) NOT NULL,

  PRIMARY KEY (`project_id`,`useragent_id`)

) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Modify the default delimiter for procedures
DELIMITER //

CREATE PROCEDURE `sp_calculate_project_updated` (
  IN p_run_id INT,
  IN p_updated BINARY(14),
  IN p_useragent_id VARCHAR(255)
)
BEGIN
  -- find project_id for current run
  SELECT jobs.project_id
  INTO @project_id
  FROM runs
    INNER JOIN jobs ON jobs.id = runs.job_id
  WHERE runs.id = p_run_id;

  -- update project_updated, set max_updated for current useragent and user
  -- we are assuming here that the application only sets the updated column to current timestamp
  -- which always should be the highest value
  -- the alternative is to run MAX() function to find the actual highest updated value
  -- decision was made that running MAX() is too resource intensive

  SELECT COUNT(*)
  INTO @rowcount
  FROM project_updated
  WHERE project_id = @project_id
        AND useragent_id = p_useragent_id;

  IF @rowcount = 0 THEN
    INSERT project_updated ( project_id, useragent_id, updated )
      VALUES ( @project_id, p_useragent_id, p_updated );
  ELSE
    UPDATE project_updated
    SET updated = p_updated
    WHERE project_id = @project_id
      AND useragent_id = p_useragent_id;
  END IF;
END //

CREATE TRIGGER `trg_insert_run_useragent`
AFTER INSERT ON `run_useragent`
FOR EACH ROW BEGIN
  CALL sp_calculate_project_updated (
    NEW.run_id,
    NEW.updated,
    NEW.useragent_id
  );
END //

CREATE TRIGGER `trg_update_run_useragent`
AFTER UPDATE ON `run_useragent`
FOR EACH ROW BEGIN
  CALL sp_calculate_project_updated (
    NEW.run_id,
    NEW.updated,
    NEW.useragent_id
  );
END //

-- Restore the default delimiter
DELIMITER ;