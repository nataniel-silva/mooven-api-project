CREATE DATABASE mooven;
CREATE USER mooven WITH PASSWORD 'mooven';
GRANT CONNECT ON DATABASE mooven TO mooven;
GRANT ALL PRIVILEGES ON DATABASE mooven TO mooven;
GRANT SELECT ON ALL TABLES IN SCHEMA public TO mooven;

CREATE SEQUENCE favorite_repository_seq;
CREATE TABLE favorite_repository(
	id INTEGER NOT NULL DEFAULT NEXTVAL('favorite_repository_seq'),
	owner TEXT NOT null,
	name TEXT NOT null,
	html_url TEXT NOT NULL,
	active BOOLEAN NOT NULL DEFAULT TRUE
);

ALTER SEQUENCE favorite_repository_seq OWNED BY favorite_repository.id;