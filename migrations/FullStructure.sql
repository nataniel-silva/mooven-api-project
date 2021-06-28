--
-- PostgreSQL database dump
--

-- Dumped from database version 10.13
-- Dumped by pg_dump version 10.13

SET statement_timeout = 0;
SET lock_timeout = 0;
SET idle_in_transaction_session_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SELECT pg_catalog.set_config('search_path', '', false);
SET check_function_bodies = false;
SET xmloption = content;
SET client_min_messages = warning;
SET row_security = off;

--
-- Name: plpgsql; Type: EXTENSION; Schema: -; Owner:
--

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;


--
-- Name: EXTENSION plpgsql; Type: COMMENT; Schema: -; Owner:
--

COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';


SET default_tablespace = '';

SET default_with_oids = false;

--
-- Name: favorite_repository; Type: TABLE; Schema: public; Owner: mooven
--

CREATE TABLE public.favorite_repository (
    id integer NOT NULL,
    owner text NOT NULL,
    name text NOT NULL,
    html_url text NOT NULL,
    active boolean DEFAULT true NOT NULL
);


ALTER TABLE public.favorite_repository OWNER TO mooven;

--
-- Name: favorite_repository_seq; Type: SEQUENCE; Schema: public; Owner: mooven
--

CREATE SEQUENCE public.favorite_repository_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;


ALTER TABLE public.favorite_repository_seq OWNER TO mooven;

--
-- Name: favorite_repository_seq; Type: SEQUENCE OWNED BY; Schema: public; Owner: mooven
--

ALTER SEQUENCE public.favorite_repository_seq OWNED BY public.favorite_repository.id;


--
-- Name: favorite_repository id; Type: DEFAULT; Schema: public; Owner: mooven
--

ALTER TABLE ONLY public.favorite_repository ALTER COLUMN id SET DEFAULT nextval('public.favorite_repository_seq'::regclass);


--
-- Data for Name: favorite_repository; Type: TABLE DATA; Schema: public; Owner: mooven
--

COPY public.favorite_repository (id, owner, name, html_url, active) FROM stdin;


-- PostgreSQL database dump complete
--
