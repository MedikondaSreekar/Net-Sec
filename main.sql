CREATE DATABASE netsecdb;

\c netsecdb

CREATE TABLE IF NOT EXISTS users (
    username TEXT NOT NULL,
    email TEXT NOT NULL,
    password TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS profiles (
    username TEXT,
    biography TEXT,
    image TEXT,
    PRIMARY KEY (username)
);

CREATE TABLE IF NOT EXISTS bankbalance (
    username TEXT,
    balance INTEGER,
    PRIMARY KEY (username)
);

CREATE TABLE IF NOT EXISTS transactions (
    from_user TEXT,
    to_user TEXT,
    amount INTEGER,
    comment TEXT,
    date DATE,
    status TEXT
);