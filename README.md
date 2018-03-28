1. Install Spectrum System Service

`mv spectrum.service.default /etc/systemd/system/spectrum-websitename.service`

2. Edit Service

Fill in all the variables for your website (path, name, logfile, dependencies)

3. Reload System Daemon

`systemctl daemon-reload`

4. Enable Service (so it auto-starts on reboot)

`systemctl enable spectrum-<website_name>`

5. Start Service

`service spectrum-<website_name> start`
