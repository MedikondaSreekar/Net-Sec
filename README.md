# Instructions to Run

1. Run the `backend_runscript.sh` to get the container up and running. Please make sure you give the correct absolute path to the shared volume to place your backend code.
2. After that please run the following to start the shell of the container
```
docker exec -it backendcontainer /bin/bash
```
3. Now run the following commands in the shell
```
sudo -u postgres pg_ctlcluster 14 main start # to start the postgresql service, please check the password in the Dockerfile
cd ~/Documents/NetSec/
sh runserver.sh # to start the server
psql -h localhost -U postgres -f main.sql # to create the database
```
4. Edit the /etc/php/8.1/cli/php.ini at the following line-
```
disable_functions = show_source, exec, shell_exec, system, passthru, proc_open, popen, curl_exec, curl_multi_exec, parse_ini_file, show_source
```
Reference: https://www.vaadata.com/blog/php-security-best-practices-vulnerabilities-and-attacks/


Note:
Please check the credentials in the following -
1. PSQL credentials
2. Ports of the frontend and backend