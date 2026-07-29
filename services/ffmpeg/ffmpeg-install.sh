echo "Installing FFmpeg... Please wait..."

wget https://johnvansickle.com/ffmpeg/releases/ffmpeg-release-amd64-static.tar.xz -O ffmpeg.tar.xz

mkdir -p ./bin

tar -xvf ffmpeg.tar.xz -C ./bin  --strip-components=1

rm ffmpeg.tar.xz

chmod +x ./bin/ffmpeg
chmod +x ./bin/ffprobe

echo "FFmpeg installed successfully."