nl.sp.spodoosync
================

SP Specific implementation for syncing between CiviCRM and Odoo

This module extends the functionality specified in the https://github.com/civicoop/org.civicoop.odoosync module

Functionality
=============

CiviCRM Contact to Odoo Partner synchronisation
-----------------------------------------------

CiviCRM Contacts are only synchronized when the field Contact in Odoo is set to yes.

This module alters the following **contact** parameters to be pushed to Odoo

- Stores Individuals as a company in Odoo because that is what the SP is using for multiple addresses.
- Sets the title of a partner based on the Gender in CiviCRM
- Sets the initials, firstname, prename (= middle name) and lastname
- Birthdate is set
- Field retourpost is set

This module also extends the default contact to partner synchronisator to find partners in Odoo based on:

- CiviCRM id field in Odoo
- Aware id field in Odoo
- Or when contact is an SP Afdeling or SP Provincie in CiviCRM it tries to find the
 matching contact based on the name of the afdeling or provincie.
 
 
CiviCRM Contribution to Odoo Invoice synchronisation
----------------------------------------------------

The contribution synchronisation is extended by adding the following checks:

- An contributions dating earlier then 2015 are not pushed to Odoo; existing draft invoices will be deleted and existing booked invoices will be credited.
- Contributions with status Refunded are not pushed to Odoo; existing draft invoices will be deleted and existing booked invoices will be credited
- Contributions linked to CiviCRM events are not pushed to Odoo; existing draft invoices will be deleted and existing booked invoices will be credited
- A contribution which is linked to a membership is not pushed to Odoo when the membership has the status Pending; existing invoices in Odoo will be left untoched. 
 

API
===

OdooInvoice.Updatecontribution
------------------------------

The API function OdooInvoice.Updatecontribution is added to let Odoo update the status of a contribution.
This wasn't possible with the normal contribution.create api because that
api call checked whether the status changed was valid (e.g. from completed to cancelled) however it 
was not possible to update the status from *completed* to *storno* 
