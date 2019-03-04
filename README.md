# ladi_book_batch
A module for batch ingest of books for Mellon Grant Project

Notes:
Batch Form requires mysql table

mysql> describe batch_queue;

Field (Type)
--------------------
batchID varchar(255)

namespace varchar(25)

collection varchar(255)

location varchar(50)

userID int(5)

userEmail varchar(50)

userName varchar(50)

batchType int(2)

status int(15) (0 initially, updated to timestamp upon ingest)

