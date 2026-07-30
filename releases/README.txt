Daher Phone - update releases
==============================

Contents
--------
  DaherPhone-update-1.3.1.zip   the update package (code + migrations)
  update.json                   the feed the app checks (version, url, sha256, notes)

The link to paste into the app (Updates -> Update server address):

    http://localhost/daher%20store/releases/update.json

This link works wherever this machine's Apache is reachable:
  - on this computer:            http://localhost/daher%20store/releases/update.json
  - from another PC on the LAN:  http://<this-pc-ip>/daher%20store/releases/update.json
    (%20 is the URL form of the space in the folder name "daher store")

For a customer OUTSIDE your network, host the two files publicly
(GitHub Releases or any web hosting):

  1. Upload DaherPhone-update-1.3.1.zip somewhere public.
  2. Edit update.json -> replace the "url" value with the zip's public URL.
     (Do NOT modify the zip afterwards - the sha256 must keep matching.)
  3. Upload update.json next to it.
  4. Give the shop the update.json URL once; it is saved in their settings.

No internet at the shop? Copy the .zip to a USB stick and use
Updates -> "Install package" instead. Same safety pipeline either way:
database backup first, automatic rollback if anything fails.

New releases: bump the VERSION file, run  deploy\build.ps1 -UpdateZip,
copy the new zip from deploy\build\ into this folder and update update.json.
This folder is excluded from packages and update zips automatically.
