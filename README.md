# Taiko no Tatsujin Practice Bot

Practice Taiko no Tatsujin within Telegram. Inspired by [this screenshot](https://t.me/yingyoushadiao/3149).

<https://t.me/taiko_no_tatsujin_practice_bot>

## Features

* Free play!
* Different button styles!
* No loses!
* Combos!

## Setup

Go to BotFather, create a bot token. Then generate a random webhook token yourself. Add a Matomo website. Make sure to have cURL and APCu extensions of PHP.

```
git clone https://github.com/FiveYellowMice/taiko-practice-bot
cd taiko-practice-bot
composer install
mkdir data
chown -R apache:apache data
chcon -Rt httpd_sys_rw_content data
cp config.example.php config.php
vim config.php
vim /etc/nginx/nginx.conf # Deny access to data directory
```
