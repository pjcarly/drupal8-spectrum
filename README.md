## Spectrum is a Drupal 8 framework.

### Features
1. ORM with built-in support for:
   1. Domain Driven Development (DDD)
   2. UnitOfWork Design Pattern
   3. jsonapi.org serialization / deserialization.
   4. Triggers routed to DDD model classes
2. Simple API Handlers through Symfony2 routing
   * Includes Basic permission checker, that checks on entities, individual fields and API endpoints
   * CORS Support
   * Customizable actions that dont fit in standard REST
3. A basic Message Broker and Async Job handler
4. Email and PDF template generation


### Systemd service
Below are the steps to write your own systemd service to start the message broker.

1. Install Spectrum System Service

`cp spectrum.service.default /etc/systemd/system/spectrum-websitename.service`

2. Edit Service

Fill in all the variables for your website (path, name, logfile, dependencies)

3. Reload System Daemon

`systemctl daemon-reload`

4. Enable Service (so it auto-starts on reboot)

`systemctl enable spectrum-<website_name>`

5. Start Service

`service spectrum-<website_name> start`
