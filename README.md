[2019-02-13 22:51:26] production.ERROR: PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '132-57179' for key 'UNIQUE_INDEX' in /var/www/api/vendor/doctrine/dbal/lib/Doctrine/DBAL/Driver/PDOStatement.php:105 Stack trace: 
#0 /var/www/api/vendor/doctrine/dbal/lib/Doctrine/DBAL/Driver/PDOStatement.php(105): PDOStatement->execute(NULL) 
#1 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Connection.php(458): Doctrine\DBAL\Driver\PDOStatement->execute() 
#2 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Connection.php(657): Illuminate\Database\Connection->Illuminate\Database\{closure}('insert into `vi...', Array) 
#3 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Connection.php(624): Illuminate\Database\Connection->runQueryCallback('insert into `vi...', Array, Object(Closure)) 
#4 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Connection.php(459): Illuminate\Database\Connection->run('insert into `vi...', Array, Object(Closure)) 
#5 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Connection.php(411): Illuminate\Database\Connection->statement('insert into `vi...', Array) 
#6 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Query/Processors/Processor.php(32): Illuminate\Database\Connection->insert('insert into `vi...', Array) 
#7 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Query/Builder.php(2137): Illuminate\Database\Query\Processors\Processor->processInsertGetId(Object(Illuminate\Database\Query\Builder), 'insert into `vi...', Array, 'id') 
#8 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(1270): Illuminate\Database\Query\Builder->insertGetId(Array, 'id') 
#9 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php(707): Illuminate\Database\Eloquent\Builder->__call('insertGetId', Array) 
#10 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php(672): Illuminate\Database\Eloquent\Model->insertAndSetId(Object(Illuminate\Database\Eloquent\Builder), Array) 
#11 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php(535): Illuminate\Database\Eloquent\Model->performInsert(Object(Illuminate\Database\Eloquent\Builder)) 
#12 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(753): Illuminate\Database\Eloquent\Model->save() 
#13 /var/www/api/vendor/laravel/framework/src/Illuminate/Support/helpers.php(1035): Illuminate\Database\Eloquent\Builder->Illuminate\Database\Eloquent\{closure}(Object(App\Models\VirtualIdolOwner)) 
#14 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Builder.php(754): tap(Object(App\Models\VirtualIdolOwner), Object(Closure)) 
#15 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php(1455): Illuminate\Database\Eloquent\Builder->create(Array) 
#16 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Eloquent/Model.php(1467): Illuminate\Database\Eloquent\Model->__call('create', Array) 
#17 /var/www/api/app/Api/v1/Controllers/CartoonRoleController.php(1316): Illuminate\Database\Eloquent\Model::__callStatic('create', Array) 
#18 [internal function]: App\Api\V1\Controllers\CartoonRoleController->makeDeal(Object(Dingo\Api\Http\Request)) 
#19 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Controller.php(54): call_user_func_array(Array, Array) 
#20 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/ControllerDispatcher.php(45): Illuminate\Routing\Controller->callAction('makeDeal', Array) 
#21 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Route.php(212): Illuminate\Routing\ControllerDispatcher->dispatch(Object(Illuminate\Routing\Route), Object(App\Api\V1\Controllers\CartoonRoleController), 'makeDeal') 
#22 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Route.php(169): Illuminate\Routing\Route->runController() 
#23 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Router.php(658): Illuminate\Routing\Route->run() 
#24 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Pipeline.php(30): Illuminate\Routing\Router->Illuminate\Routing\{closure}(Object(Dingo\Api\Http\Request)) 
#25 /var/www/api/app/Http/Middleware/Auth.php(63): Illuminate\Routing\Pipeline->Illuminate\Routing\{closure}(Object(Dingo\Api\Http\Request)) 
#26 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(149): App\Http\Middleware\Auth->handle(Object(Dingo\Api\Http\Request), Object(Closure)) 
#27 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Pipeline.php(53): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}(Object(Dingo\Api\Http\Request)) 
#28 /var/www/api/vendor/dingo/api/src/Http/Middleware/PrepareController.php(45): Illuminate\Routing\Pipeline->Illuminate\Routing\{closure}(Object(Dingo\Api\Http\Request)) 
#29 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(149): Dingo\Api\Http\Middleware\PrepareController->handle(Object(Dingo\Api\Http\Request), Object(Closure)) 
#30 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Pipeline.php(53): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}(Object(Dingo\Api\Http\Request)) 
#31 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(102): Illuminate\Routing\Pipeline->Illuminate\Routing\{closure}(Object(Dingo\Api\Http\Request)) 
#32 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Router.php(660): Illuminate\Pipeline\Pipeline->then(Object(Closure)) 
#33 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Router.php(635): Illuminate\Routing\Router->runRouteWithinStack(Object(Illuminate\Routing\Route), Object(Dingo\Api\Http\Request)) 
#34 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Router.php(601): Illuminate\Routing\Router->runRoute(Object(Dingo\Api\Http\Request), Object(Illuminate\Routing\Route)) 
#35 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Router.php(590): Illuminate\Routing\Router->dispatchToRoute(Object(Dingo\Api\Http\Request)) 
#36 /var/www/api/vendor/dingo/api/src/Routing/Adapter/Laravel.php(81): Illuminate\Routing\Router->dispatch(Object(Dingo\Api\Http\Request)) 
#37 /var/www/api/vendor/dingo/api/src/Routing/Router.php(512): Dingo\Api\Routing\Adapter\Laravel->dispatch(Object(Dingo\Api\Http\Request), 'latest') 
#38 /var/www/api/vendor/dingo/api/src/Http/Middleware/Request.php(126): Dingo\Api\Routing\Router->dispatch(Object(Dingo\Api\Http\Request)) 
#39 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(114): Dingo\Api\Http\Middleware\Request->Dingo\Api\Http\Middleware\{closure}(Object(Dingo\Api\Http\Request)) 
#40 /var/www/api/vendor/barryvdh/laravel-cors/src/HandlePreflight.php(46): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}(Object(Dingo\Api\Http\Request)) 
#41 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(149): Barryvdh\Cors\HandlePreflight->handle(Object(Dingo\Api\Http\Request), Object(Closure)) 
#42 /var/www/api/app/Http/Middleware/Csrf.php(21): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}(Object(Dingo\Api\Http\Request)) 
#43 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(149): App\Http\Middleware\Csrf->handle(Object(Dingo\Api\Http\Request), Object(Closure)) 
#44 /var/www/api/vendor/barryvdh/laravel-cors/src/HandleCors.php(59): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}(Object(Dingo\Api\Http\Request)) 
#45 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(149): Barryvdh\Cors\HandleCors->handle(Object(Dingo\Api\Http\Request), Object(Closure)) 
#46 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(102): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}(Object(Dingo\Api\Http\Request)) 
#47 /var/www/api/vendor/dingo/api/src/Http/Middleware/Request.php(127): Illuminate\Pipeline\Pipeline->then(Object(Closure)) 
#48 /var/www/api/vendor/dingo/api/src/Http/Middleware/Request.php(103): Dingo\Api\Http\Middleware\Request->sendRequestThroughRouter(Object(Dingo\Api\Http\Request)) 
#49 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(149): Dingo\Api\Http\Middleware\Request->handle(Object(Dingo\Api\Http\Request), Object(Closure)) 
#50 /var/www/api/vendor/laravel/framework/src/Illuminate/Routing/Pipeline.php(53): Illuminate\Pipeline\Pipeline->Illuminate\Pipeline\{closure}(Object(Illuminate\Http\Request)) 
#51 /var/www/api/vendor/laravel/framework/src/Illuminate/Pipeline/Pipeline.php(102): Illuminate\Routing\Pipeline->Illuminate\Routing\{closure}(Object(Illuminate\Http\Request)) 
#52 /var/www/api/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(151): Illuminate\Pipeline\Pipeline->then(Object(Closure)) 
#53 /var/www/api/vendor/laravel/framework/src/Illuminate/Foundation/Http/Kernel.php(116): Illuminate\Foundation\Http\Kernel->sendRequestThroughRouter(Object(Illuminate\Http\Request)) 
#54 /var/www/api/public/index.php(53): Illuminate\Foundation\Http\Kernel->handle(Object(Illuminate\Http\Request)) 
#55 {main}  Next Doctrine\DBAL\Driver\PDOException: SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry '132-57179' for key 'UNIQUE_INDEX' in /var/www/api/vendor/doctrine/dbal/lib/Doctrine/DBAL/Driver/PDOStatement.php:107 Stack trace: 
#0 /var/www/api/vendor/laravel/framework/src/Illuminate/Database/Connection.php(458): Doctrine\DBAL\Driver\PDOStatement->execute()