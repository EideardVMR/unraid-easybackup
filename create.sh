version="2023.11.11"

tar -cf "/usr/local/emhttp/plugins/easybackup-$version.tar" "/usr/local/emhttp/plugins/easybackup/"
xz -c -z "/usr/local/emhttp/plugins/easybackup-$version.tar" > "/usr/local/emhttp/plugins/easybackup-$version.txz"
md5sum "/usr/local/emhttp/plugins/easybackup-$version.txz"
rm "/usr/local/emhttp/plugins/easybackup-$version.tar"
