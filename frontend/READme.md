# Instructions to Run
1. After running the `frontend_runscript.sh` please run the following commands in the container.
2. Now access the script of the frontend_container
```
docker exec -it frontend_container /bin/bash
```
3. Now run the following commands in the container in the shell of the container
```
apt update && apt install -y python3
cd /frontend
python3 -m http.server 8083
```

You can find your website at http://localhost:8083