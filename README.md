# unraid-easybackup
This Unraid plugin is designed to help you easily make backups of your VMs and Docker containers.

## What is currently implemented
- Creating snapshot-based backups of VMs (vDisk Backup, vDisk Snapshot).
- Creating container backups. The appdata share is not simply backed up, but the binds of the containers are determined and backed up. Individual binds can be ignored if they are not to be affected by the backup. 
- Create, commit and restore VM snapshots (vDisk snapshot)
- Create a backup hirachie (if desired). Example: the last 7 days, 4 weeks, 12 months, 10 years. In each case the last backup is kept.
- Deleted files can be moved to a trash can instead of actually deleting them. This works well with the plugin "Recycle Bin".
- Backup compressed (Zip or GZ) and uncompressed.
- A notification via Gotify on successful and/or failed automatic backup.

## Planned features
- Restore a backup by pressing a button (button is already present but without function).
- Copy the last backup to a removable disk. 
- Enable automatic backups (currently this must be set up manually for security reasons).

## Known issues
- Backup summary via Unraid notifications counts the backed up files incorrectly!

## Set up automatic backup
For security reasons, the automatic backup is not set up by the plugin - you have to do this yourself!

> **Warning**
> You should always perform a manual test run before activating the automatic backup! Only when the backups have been performed manually without any problems, you should activate the automatic backup.

You can simply store the automatic backup as a script in userscript. See here:
![image](https://github.com/EideardVMR/unraid-easybackup/assets/143615696/145f5b1e-b3fc-438d-af4d-5ed9106e0ac8)

You can also back up individual VMs or containers automatically. To do this, you only have to execute "backup vm [name of VM]" or "backup container [name of container]" instead of "backup all". However, notifications are only sent for "backup all"!

## Startpage
In the dashboard you can see which VM or container consumes how much memory.

![image](https://github.com/EideardVMR/unraid-easybackup/assets/143615696/12b31fd6-52fc-4b7f-b6f5-e8f72c5f8679)

## Snapshot Manager
In the snapshot manager you can start and stop VMs, create snapshots, perform a commit or revert a snapshot.
In the picture you can see that the VM "Ubuntu" has a snapshot. 

![image](https://github.com/EideardVMR/unraid-easybackup/assets/143615696/1878ae2f-8d81-4c13-8e5f-be309642252b)

> **Note**
> - Snapshots can only be created from VMs that have been started.
> - Commits can only be executed by VMs that have been started.
> - Restores can only be executed with stopped VMs.

## Backup VMs
In this section you can see the saved backups by clicking on the VM name. Also shown is when a backup was created, how many backups there are, and how much disk space is used. 
With a click on "Backup Now" you can initiate a backup manually. The button "Delete" deletes a backup. (This currently only works for compressed backups). The "Restore" button is currently disabled. This is to enable an automatic restore of a backup. 

If you do not want individual VMs to be backed up, you can exclude them from the automatic backup using the checkbox. Remember to click "Save"!

## Container
All available containers and their binds will be listed. The same rules apply to the checkboxes and buttons as for "Backup VMs". An additional function for the containers is the deactivation of individual binds. 

An example: You have a Plex server as container. This has 3 binds. One stores the configuration, one stores the transcoding and one stores the media which is mostly on the array and not in the cache. For disk space reasons it would not be advisable to save the several GB media storage which is already on the array. So Easybackup will suggest you not to backup this bind. You should comply with this, unless you are sure!

![image](https://github.com/EideardVMR/unraid-easybackup/assets/143615696/59c91eed-0ea7-4006-9099-7d914ca299e1)

## Settings
### VMs and Container
Activate the automatic backup of VMs or containers here. This function currently only ensures that the VMs and containers that are not to be ignored are backed up using the "backup all" job. In addition, you specify a path where the backups are saved. I recommend to create a share (for example: backup_internal) in which you create a folder named "domains" and "appdata".

> **Warning**
> You can leave the snapshot extension as it is. If you want a special extension you can change it here. But be careful: NEVER change the extension if you have backups that contain a snapshot or if snapshots of VMs currently exist! This will cause Easybackup not to recognize the snapshot anymore.

### Compression
When compressing, you can choose between Zip and GZ. With GZ, a tar archive is packed using gZip. Mostly the GZip compression is faster, but it has the disadvantage that you cannot extract single files from the backup without unpacking the whole file.
Therefore Zip is selected as default.

> **Note**
> If you are worried about the file permissions not being preserved with Zip, I can reassure you. In the Zip file (also with GZ or uncompressed) a "fileinfo.json" is always stored. This contains all the information about the ownership permissions of the files. The automatic restore should use this file in the future to restore all permissions.

### Backups
Unlike many other backup systems for Unraid, Easybackup can take into account different time periods. For example, you can keep one backup of each of the last 7 days (daily), then one of each of the last 4 weeks, and so on. 
However, if you want to go the conventional route, you can simply set the week, month and year to 0 and enter as many days as you want to keep as a backup each day. 


### Recycle Bin
In order to avoid data loss and to get used to how Easybackup works, a trash can was created instead of delete. This ensures that backup files are moved to the recycle bin instead of deleting them. If you have installed the plugin "Recycle Bin", Recycle Bin will collect the files and handle them according to its settings. To use Recycle Bin the folder must be named ".Recycle.Bin"! The default setting of Easybackup is prepared for all backups to be located in the same share "backup_internal" and moved to the trash garbage can there. Please note that moving the files to other hard disks will take much longer! Please read in advance how the Unraid Array works.

### Gotify
Gotify is a self-hosted push service. You can run it as Docker on your Unraid and use the app to get notifications sent to your device. For example, Easybackup can notify you with a custom text when the automatic backup has completed successfully. But also when an error has occurred.

### Logs
Logs are important! Logs can help to find problems. Normally only warnings and errors are written. However, you can increase the log level (if you have difficulty finding an error). "Info" gives you much more information about what Easybackup is currently doing. With Debug almost every operation of Easybackup is logged. I use this level for development, but it can also be helpful if a very unusual error occurs. 

Normally you should set the log level to 2, i.e. Warning. The higher levels create a lot of entries in the log file which needs a lot of memory. Currently Easybackup is not yet able to clean the log file. This function is to come, but has no priority.

Since I am a German developer, some text passages were translated with the help of DeepL. If there are any errors, please feel free to contact me!
