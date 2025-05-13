# 📦 Laravel Package: DeployTaggedRelease

## Overview

DeployTaggedRelease is a Laravel Artisan command designed to:

* Deploy a specific **Git tag** from a configured repository.
* Install dependencies using **Composer** after pulling the code.
* Optionally **create missing tables and columns** in the database (without affecting existing structure).
* Use a **GitHub personal access token** for authenticated repo access.
* Avoid Laravel’s migration system — ideal for rapid deployment or staging setups.

## ✅ Features

* Deploy from any GitHub (or Git) repo using a Git tag.
* Specify schema updates inline without needing migrations.
* Auto-create tables if missing.
* Auto-add columns if not present (but never alter or drop anything).
* Use current DB connection if not overridden.
* Full configuration via config/deploy.php.

## 📦 Installation

### 1. Require via Composer

bash

CopyEdit

composer require your-vendor/deploy-tagged-release

Replace your-vendor/deploy-tagged-release with your actual Packagist package name once published.

### 2. Register the Service Provider (if not auto-discovered)

In config/app.php:

php

CopyEdit

'providers' => [

// ...

YourVendor\DeployRelease\DeployTaggedReleaseServiceProvider::class,

],

### 3. Publish Configuration File

bash

CopyEdit

php artisan vendor:publish --tag=deploy-config

This creates a config file at:

arduino

CopyEdit

config/deploy.php

## ⚙️ Configuration

In config/deploy.php, define your deployment parameters:

php

CopyEdit

return [

// Remote Git repository URL

'repo' => env('DEPLOY\_REPO', 'https://github.com/your-org/your-repo.git'),

// Local path to clone/pull the repo into

'path' => env('DEPLOY\_PATH', base\_path('deployed')),

// Optional: Name of database to use (leave null to use current DB)

'db' => env('DEPLOY\_DB', null),

// Optional: GitHub personal access token for private repos

'token' => env('DEPLOY\_GIT\_TOKEN', null),

];

Place your GitHub token in .env as DEPLOY\_GIT\_TOKEN=ghp\_xxxxxxxx.

## 🚀 Usage

### Basic Command

bash

CopyEdit

php artisan deploy:tagged-release {tag} [--schema=...]

* {tag}: Git tag to deploy (e.g., v1.0.2)
* --schema: Optional semicolon-separated schema definition string.

### Examples

#### ✅ Deploy a Tag Only

bash

CopyEdit

php artisan deploy:tagged-release v1.0.0

* Clones or pulls the repo
* Checks out tag v1.0.0
* Installs composer dependencies
* Uses current DB
* Skips schema creation

#### ✅ Deploy with Table Creation

bash

CopyEdit

php artisan deploy:tagged-release v1.1.0 \

--schema="users:id:integer,name:string,email:string;posts:id:integer,title:string,body:text"

* If users or posts tables do not exist, they are created.
* If they exist, new columns are added (but existing columns remain untouched).
* Fields use Laravel column types: string, integer, text, boolean, timestamp, etc.

#### ✅ With Custom DB and Private Repo

In your .env:

env

CopyEdit

DEPLOY\_REPO=https://github.com/your-org/your-private-repo.git

DEPLOY\_DB=staging\_db

DEPLOY\_PATH=/var/www/releases

DEPLOY\_GIT\_TOKEN=ghp\_yourTokenHere

Then run:

bash

CopyEdit

php artisan deploy:tagged-release v2.0.1 --schema="logs:id:integer,message:string"

## 🛠 Schema Syntax Details

Each --schema entry follows this format:

lua

CopyEdit

table:col:type,col:type;table2:col:type

Example:

bash

CopyEdit

php artisan deploy:tagged-release v3.0.0 \

--schema="products:id:integer,name:string,price:integer;orders:id:integer,product\_id:integer,quantity:integer"

## 💡 Notes

* This package **does not use Laravel migrations**.
* It **does not drop or modify existing columns**.
* It is ideal for quick deployments, CI/CD pipelines, and staging automation.
