Usage related addendum to README



Report files.
------------
Suggested path to reports on Centreon server: /var/www/reports/db/

Make sure this directory exists and that db/ is writable for the web-server.  

mkdir /var/www/reports
mkdir /var/www/reports/db
chown -R apache /var/www/reports

Report templates
----------------
Find Test-template.odt from the repo and copy to /var/www/reports/
Create your own versions. Each report can specify which template to use. Make sure to use Libreoffice for .odt templates and Word under Windows for .docx. TinyButStrong tends to screw up advances formatting when using non-native word processor.

Logo
----
Copy your logo ( gif|png) to 
  /usr/share/centreon/www/modules/pdfreports/img/headers/

The logo should then be selectable in the GUI admin setup.


Initial config:
---------------
Centreon GUI: Administration  >  Parameters  >  PDF Reports


Defining reports
----------------

Configuration  >  PDF Reports   ===> Add

In Report Template you must fill inn full path to the template. ( docx or odt )



Cron to automate reports.
-------------------------

Review /etc/cron.d/pdfreports to make sure you execute the reports at intervals matching what you configure in the gui. Also, make sure the Centreon jobs to build the statistics are allowed to finish before you start the reports.
See /etc/cron.d/centreon:
eventReportBuilder 
dashboardBuilder

NOTE: Using -r  ass suggested by Centreon to rebuild statistics deletes old history possibly up to NOW! Se bug https://github.com/centreon/centreon/issues/6802


