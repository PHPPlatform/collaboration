
-- Drop Tables 

DROP TABLE IF EXISTS groups_accounts;
DROP TABLE IF EXISTS groups;
DROP TABLE IF EXISTS organization_person;
DROP TABLE IF EXISTS organization;
DROP TABLE IF EXISTS login_history;
DROP TABLE IF EXISTS login_details;
DROP TABLE IF EXISTS person_role;
DROP TABLE IF EXISTS person;
DROP TABLE IF EXISTS role;
DROP TABLE IF EXISTS account;
DROP TABLE IF EXISTS contact;
DROP TABLE IF EXISTS person_temp;




-- Create Tables 

CREATE TABLE account
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    ACCOUNT_NAME varchar(15) NOT NULL,
    CREATED timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    MODIFIED timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
    CREATED_BY_ID bigint(10) unsigned DEFAULT NULL,
    CONTACT_ID bigint(10) unsigned DEFAULT NULL,
    PRIMARY KEY (ID),
    UNIQUE (ACCOUNT_NAME)
);


CREATE TABLE contact
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    INFO text NOT NULL COMMENT 'json object with contact information , 
Application modules can decide on the schema of this json object',
    PRIMARY KEY (ID)
);


CREATE TABLE groups
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    ACCOUNT_ID bigint(10) unsigned NOT NULL,
    NAME varchar(20),
    PRIMARY KEY (ID),
    UNIQUE (ACCOUNT_ID)
);


CREATE TABLE groups_accounts
(
    GROUP_ID bigint(10) unsigned NOT NULL,
    ACCOUNT_ID bigint(10) unsigned NOT NULL,
    ACCOUNT_TYPE enum('ORGANIZATION','PERSON','GROUPS','ROLE') NOT NULL,
    UNIQUE (GROUP_ID, ACCOUNT_ID)
);


CREATE TABLE login_details
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    PERSON_ID bigint(10) unsigned NOT NULL,
    LOGIN_NAME varchar(50) NOT NULL,
    PASSWORD varchar(100) NOT NULL,
    STATUS enum('ACTIVE','PENDING_VERIFICATION','DISABLED') NOT NULL DEFAULT 'PENDING_VERIFICATION',
    PRIMARY KEY (ID),
    UNIQUE (LOGIN_NAME)
);


CREATE TABLE login_history
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    LOGINDETAILS_ID bigint(10) unsigned NOT NULL,
    TYPE enum('REGISTRATION','ACTIVATED','DISABLED','LOGIN','LOGOUT','PASSWORD_RESET_REQUEST','PASSWORD_RESET_REQUEST_VALIDATED','PASSWORD_RESET') NOT NULL,
    TIME timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
    LOGIN_IP varchar(15),
    SESSION_ID varchar(100),
    PRIMARY KEY (ID)
);


CREATE TABLE organization
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    ACCOUNT_ID bigint(10) unsigned NOT NULL,
    NAME varchar(100),
    DOI date COMMENT 'Date of Incorporation',
    TYPE varchar(20),
    PARENT_ID bigint(10) unsigned,
    PRIMARY KEY (ID),
    UNIQUE (ACCOUNT_ID)
);


CREATE TABLE organization_person
(
    ORGANIZATION_ID bigint(10) unsigned NOT NULL,
    PERSON_ID bigint(10) unsigned NOT NULL,
    UNIQUE (ORGANIZATION_ID, PERSON_ID)
);


CREATE TABLE person
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    ACCOUNT_ID bigint(10) unsigned NOT NULL,
    FIRST_NAME varchar(50),
    MIDDLE_NAME varchar(50),
    LAST_NAME varchar(50),
    DOB date COMMENT 'Date of Birth',
    GENDER enum('MALE','FEMALE','NONE'),
    PRIMARY KEY (ID),
    UNIQUE (ACCOUNT_ID)
);


CREATE TABLE person_role
(
    PERSON_ID bigint(10) unsigned NOT NULL,
    ROLE_ID bigint(10) unsigned NOT NULL,
    UNIQUE (PERSON_ID, ROLE_ID)
);


CREATE TABLE role
(
    ID bigint(10) unsigned NOT NULL AUTO_INCREMENT,
    ACCOUNT_ID bigint(10) unsigned NOT NULL,
    NAME varchar(20),
    PRIMARY KEY (ID),
    UNIQUE (ACCOUNT_ID)
);



-- Create Foreign Keys 


ALTER TABLE account
    ADD FOREIGN KEY (CONTACT_ID)
    REFERENCES contact (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;

ALTER TABLE account
    ADD FOREIGN KEY (CREATED_BY_ID)
    REFERENCES person (ID)
    ON UPDATE CASCADE
    ON DELETE SET NULL
;

ALTER TABLE groups
    ADD FOREIGN KEY (ACCOUNT_ID)
    REFERENCES account (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;

ALTER TABLE groups_accounts
    ADD FOREIGN KEY (ACCOUNT_ID)
    REFERENCES account (ID)
    ON UPDATE CASCADE
    ON DELETE CASCADE
;

ALTER TABLE groups_accounts
    ADD FOREIGN KEY (GROUP_ID)
    REFERENCES groups (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;

ALTER TABLE organization
    ADD FOREIGN KEY (ACCOUNT_ID)
    REFERENCES account (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;


ALTER TABLE person
    ADD FOREIGN KEY (ACCOUNT_ID)
    REFERENCES account (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;


ALTER TABLE role
    ADD FOREIGN KEY (ACCOUNT_ID)
    REFERENCES account (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;


ALTER TABLE login_history
    ADD FOREIGN KEY (LOGINDETAILS_ID)
    REFERENCES login_details (ID)
    ON UPDATE CASCADE
    ON DELETE CASCADE
;


ALTER TABLE organization
    ADD FOREIGN KEY (PARENT_ID)
    REFERENCES organization (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;


ALTER TABLE organization_person
    ADD FOREIGN KEY (ORGANIZATION_ID)
    REFERENCES organization (ID)
    ON UPDATE CASCADE
    ON DELETE RESTRICT
;

ALTER TABLE organization_person
    ADD FOREIGN KEY (PERSON_ID)
    REFERENCES person (ID)
    ON UPDATE CASCADE
    ON DELETE CASCADE
;

ALTER TABLE login_details
    ADD FOREIGN KEY (PERSON_ID)
    REFERENCES person (ID)
    ON UPDATE CASCADE
    ON DELETE CASCADE
;

ALTER TABLE person_role
    ADD FOREIGN KEY (PERSON_ID)
    REFERENCES person (ID)
    ON UPDATE CASCADE
    ON DELETE CASCADE
;

ALTER TABLE person_role
    ADD FOREIGN KEY (ROLE_ID)
    REFERENCES role (ID)
    ON UPDATE CASCADE
    ON DELETE CASCADE
;
