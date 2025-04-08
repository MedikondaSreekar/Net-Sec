# Run this script to start the frontend server

#!/bin/bash

CONTAINER_NAME="frontend_container"
IMAGE_NAME="ubuntu:latest"
HOST_PORT=8083
CONTAINER_PORT=8083
# Please edit the path to the frontend directory on your local machine
SHARED_VOLUME="/Users/shaikarmaan/Documents/BTech/acads/Year_3/sem6/ns/Project/frontend:/frontend"

# Pull the latest Ubuntu image
docker pull $IMAGE_NAME

docker run -d --name $CONTAINER_NAME \
  -p $HOST_PORT:$CONTAINER_PORT \
  -v $SHARED_VOLUME \
  $IMAGE_NAME tail -f /dev/null

# Display running containers
docker ps -a | grep $CONTAINER_NAME 