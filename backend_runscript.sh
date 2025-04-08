# Run this script in the backend folder
# This script will build the docker image and run the container
# The container will be running in the background
#!/bin/bash

# build the image
docker build -t backend_image .

# run the container
docker run -it -d --name backend_container -v /home/sriman/Desktop/Network_Security/Project/Project/backend:/home/netsec/Documents/NetSec -p 8081:8081 --user netsec backend_image

# start the container
docker start backend_container