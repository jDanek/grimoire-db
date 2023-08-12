Grimoire
########

.. image:: https://repository-images.githubusercontent.com/677257963/b1803baf-c64a-4975-98c9-9ea5f5425cfa

Grimoire is a PHP library for simple working with data in the database. The most interesting feature is a very easy work with table relationships. The overall performance is also very important and Grimoire can actually run faster than a native driver.

.. contents::

Requirements
************
- PHP 7.1+
- only MySQL database supported

Usage
*****

.. code:: php

   <?php

   $connection = new \Mysqli(...);
   $software = new Grimoire\Database($connection);

   foreach ($software->application()->order("title") as $application) { // get all applications ordered by title
       echo "$application[title]\n"; // print application title
       echo $application->author["name"] . "\n"; // print name of the application author
       foreach ($application->application_tag() as $application_tag) { // get all tags of $application
           echo $application_tag->tag["name"] . "\n"; // print the tag name
       }
   }

Alternatively, you can use the ``$software->table('table_name')`` to achieve the same result.
