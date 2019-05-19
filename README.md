# practical-phpfw
It is a practical and simple php framework. The framework only provides restful services to the mobile frontend and desktop. ProcDb.php encapsulates many database methods based on php pdo. You can find detail information in function db_test.

The entry of this framework is entry.php, Firstly, the frontend should send a sign which is created by md5 of post parameters. Then you can set a route map between post parameter actionUrl (not url itself) and framework classes. Attention, url doesn’t contain any route information, API is differentiated by the post parameter: actionUrl.
