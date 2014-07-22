htmlToWiki
==========

Used to convert a 800 page, 50k image site ( www.costumes.org ) from html format into a wiki. This
was my first project and my coding style/ability has improved considerably since then. I've 
committed it here to show my progression in the last five months (this was created in February).

Uses php's domxml to parse the files, stack to keep track of recursion, and cURL for uploading to
mediawiki via its api. Lots of special handling for the site I converted.
