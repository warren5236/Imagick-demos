<?php

//TODO - Arctan function isn't in my current imagick.

\Intahwebz\Functions::load();

//yolo - We use a global to allow us to do a hack to make all the code examples
//appear to use the standard 'header' function, but also capture the content type 
//of the image
$imageType = null;


/**
 * @return \Auryn\Provider
 */
function bootstrap() {

    $injector = new Auryn\Provider();
    $jigConfig = new Intahwebz\Jig\JigConfig(
        "../templates/",
        "../var/compile/",
        'tpl',
        \Intahwebz\Jig\JigRender::COMPILE_CHECK_EXISTS
    );

    $injector->share($jigConfig);

    $injector->defineParam('imageBaseURL', null);
    $injector->defineParam('customImageBaseURL', null);
    $injector->defineParam('pageTitle', "Imagick demos");
    //TODO - move these elsewhere or delete
    $injector->defineParam('standardImageWidth', 500);
    $injector->defineParam('standardImagHeight', 500);
    $injector->defineParam('smallImageWidth', 350); 
    $injector->defineParam('smallImageHeight', 300);

    $injector->alias(ImagickDemo\Control::class, ImagickDemo\Control\NullControl::class);
    $injector->alias(Intahwebz\Request::class, Intahwebz\Routing\HTTPRequest::class);
    $injector->alias(\ImagickDemo\ExampleList::class, \ImagickDemo\NullExampleList::class);
    $injector->alias(\ImagickDemo\Example::class, \ImagickDemo\NullExample::class);
    $injector->alias(\ImagickDemo\Navigation\Nav::class, \ImagickDemo\Navigation\NullNav::class);

    $injector->share(\ImagickDemo\Control::class);
    $injector->share(\ImagickDemo\Example::class);
    $injector->share(ImagickDemo\Navigation\Nav::class);

    $injector->define(ImagickDemo\DocHelper::class, [
        ':category' => null,
        ':example' => null
    ]);

    $injector->define(
    Intahwebz\Routing\HTTPRequest::class,
        array(
            ':server' => $_SERVER,
            ':get' => $_GET,
            ':post' => $_POST,
            ':files' => $_FILES,
            ':cookie' => $_COOKIE
        )
    );

    $injector->defineParam('imageCachePath', "../var/cache/imageCache/");
    $injector->share($injector); //yolo - use injector as service locator

    return $injector;
}

function setupImageDelegation(\Auryn\Provider $injector, $category, $example) {
    $function = setupExampleInjection($injector, $category, $example);
    $namespace = sprintf('ImagickDemo\%s\functions', $category);
    $namespace::load(); 
    $injector->execute($function);
    exit(0);
}

function setupExampleDelegation(\Auryn\Provider $injector, $category, $example) {
    setupExampleInjection($injector, $category, $example);
    renderTemplate($injector);
}

function setupExampleInjection(\Auryn\Provider $injector, $category, $example) {

    $exampleDefinition = getExampleDefinition($category, $example);
    $function = $exampleDefinition[0];
    $controlClass = $exampleDefinition[1];

    if (array_key_exists('defaultParams', $exampleDefinition) == true) {
        foreach($exampleDefinition['defaultParams'] as $name => $value) {
            $defaultName = 'default'.ucfirst($name);
            $injector->defineParam($defaultName, $value);
        }
    }

    $injector->defineParam('imageBaseURL', '/image/'.$category.'/'.$example);
    $injector->defineParam('customImageBaseURL', '/customImage/'.$category.'/'.$example);
    $injector->defineParam('activeCategory', $category);
    $injector->alias(\ImagickDemo\Control::class, $controlClass);
    $injector->share($controlClass);
    $injector->alias(ImagickDemo\ExampleList::class, "ImagickDemo\\".$category."\\ExampleList");

    $injector->alias(\ImagickDemo\Navigation\Nav::class, \ImagickDemo\Navigation\CategoryNav::class);
    $injector->define(ImagickDemo\Navigation\CategoryNav::class, [
        ':category' => $category,
        ':example' => $example
    ]);

    $injector->define(ImagickDemo\DocHelper::class, [
        ':category' => $category,
        ':example' => $example
    ]);

    $params = ['a', 'amount', 'amplitude', 'angle', 'b', 'backgroundColor', 'blackPoint', 'blueShift', 'brightness', 'channel', 'colorElement', 'contrast', 'colorSpace', 'distortionExample', 'endAngle', 'endX', 'endY', 'evaluateType', 'fillColor', 'firstTerm', 'fillModifiedColor', 'fourthTerm', 'g', 'gradientStartColor', 'gradientEndColor', 'height', 'image', 'imagePath', 'length', 'meanOffset', 'noiseType', 'originX', 'originY', 'r', 'radius', 'roundX', 'roundY', 'secondTerm', 'sigma', 'skew', 'solarizeThreshold', 'startAngle', 'startX', 'startY', 'statisticType', 'strokeColor', 'swirl', 'textDecoration', 'textUnderColor', 'thirdTerm', 'threshold', 'translateX', 'translateY', 'unsharpThreshold', 'virtualPixelType', 'whitePoint', 'x', 'y', 'w20', 'width', 'h20', 'sharpening', 'midpoint', 'sigmoidalContrast',];

    foreach ($params as $param) {
        $paramGet = 'get'.ucfirst($param);
        $injector->delegateParam(
                 $param,
                     [$controlClass, $paramGet]
        );
    }

    $injector->alias(\ImagickDemo\Example::class, sprintf('ImagickDemo\%s\%s', $category, $function));

    return $function;
}


function setupCustomImageDelegation(\Auryn\Provider $injector, $category, $example) {
    $function = setupExampleInjection($injector, $category, $example); 
    $className = sprintf('ImagickDemo\%s\%s', $category, $function);
    $injector->execute([$className, 'renderCustomImage']);
}


function setupCatergoryDelegation(\Auryn\Provider $injector, $category) {
    $validCatergories = [
        'Imagick',
        'ImagickDraw',
        'ImagickPixel',
        'ImagickPixelIterator',
        'Example',
    ];

    if (in_array($category, $validCatergories) == false) {
        throw new \Exception("Category is not valid.");
    }
    
    $injector->defineParam('imageBaseURL', '/image/'.$category);
    $injector->defineParam('customImageBaseURL', '/customImage/'.$category);
    $injector->defineParam('activeCategory', $category);

    $injector->share(\ImagickDemo\Control::class);
    $injector->alias(ImagickDemo\ExampleList::class, "ImagickDemo\\".$category."\\ExampleList");
    $injector->alias(\ImagickDemo\Example::class, sprintf('ImagickDemo\%s\IndexExample', $category));
    $injector->alias(\ImagickDemo\Navigation\Nav::class, \ImagickDemo\Navigation\CategoryNav::class);
    $injector->define(ImagickDemo\Navigation\CategoryNav::class, [
        ':category' => $category,
        ':example' => null
    ]);

    $injector->define(ImagickDemo\DocHelper::class, [
        ':category' => $category,
        ':example' => null
    ]);

    renderTemplate($injector);
}

function renderTemplate(\Auryn\Provider $injector) {
    $viewModel = $injector->make(Intahwebz\ViewModel\BasicViewModel::class);
    $jigRenderer = $injector->make(Intahwebz\Jig\JigRender::class);
    $jigRenderer->bindViewModel($viewModel);
    $viewModel->setVariable('pageTitle', "Imagick demos");
    $jigRenderer->renderTemplateFile('index');
}


function setupRootIndex(\Auryn\Provider $injector) {
    $injector->alias(\ImagickDemo\Example::class, ImagickDemo\HomePageExample::class);

    //TODO - setup 
    renderTemplate($injector);
}


$routesFunction = function(FastRoute\RouteCollector $r) {

    $categories = '{category:Imagick|ImagickDraw|ImagickPixel|ImagickPixelIterator|Example}';

    //Category indices
    $r->addRoute(
      'GET',
          "/$categories",
          'setupCatergoryDelegation'
    );

    //Category + example
    $r->addRoute(
        'GET',
        "/$categories/{example:[a-zA-Z]+}",
        'setupExampleDelegation'
    );

    //Images
    $r->addRoute(
      'GET',
          "/image/$categories/{example:[a-zA-Z]+}",
          'setupImageDelegation'
    );

    $r->addRoute(
      'GET',
          "/customImage/$categories/{example:[a-zA-Z]*}",
          'setupCustomImageDelegation'
    );

    $r->addRoute(
      'GET',
          "/help/$categories/{example:[a-zA-Z]+}",
          'setupHelp'
    );
    
    //root
    $r->addRoute('GET', '/', 'setupRootIndex');
};


$dispatcher = FastRoute\simpleDispatcher($routesFunction);

$httpMethod = 'GET';
$uri = '/';

if(array_key_exists('REQUEST_URI', $_SERVER)){
    $uri = $_SERVER['REQUEST_URI'];
}

//$uri = "/customImage/Imagick/sparseColorImage/renderImageVoronoi";

$path = $uri;
$queryPosition = strpos($path, '?');
if ($queryPosition !== false) {
    $path = substr($path, 0, $queryPosition);
}

$injector = bootstrap(); 
$routeInfo = $dispatcher->dispatch($httpMethod, $path);

function process(\Auryn\Provider $injector, $handler, $vars) {

    $lowried = [];
    foreach ($vars as $key => $value) {
        $lowried[':'.$key] = $value;
    }

    $injector->execute($handler, $lowried);
}


switch ($routeInfo[0]) {
    case FastRoute\Dispatcher::NOT_FOUND: {
        header("HTTP/1.0 404 Not Found", true, 404);
        echo "No route matched. No route matched.No route matched.No route matched.No route matched.No route matched.No route matched.No route matched.";
        break;
    }

    case FastRoute\Dispatcher::METHOD_NOT_ALLOWED: {
        $allowedMethods = $routeInfo[1];
        // ... 405 Method Not Allowed
        break;
    }

    case FastRoute\Dispatcher::FOUND: {
        $handler = $routeInfo[1];
        $vars = $routeInfo[2];
        // ... call $handler with $vars
        //TODO - support head?

        process($injector, $handler, $vars);
        break;
    }
}


/*

function renderImageSafe() {
    try {
        $this->renderImage();
    }
    catch(\Exception $e) {
        $draw = new \ImagickDraw();

        $strokeColor = new \ImagickPixel('none');
        $lightColor = new \ImagickPixel('brown');

        $draw->setStrokeColor($strokeColor);
        $draw->setFillColor($lightColor);
        $draw->setStrokeWidth(1);
        $draw->setFontSize(24);
        $draw->setFont("../fonts/Arial.ttf");

        $draw->setTextAntialias(false);
        $draw->annotation(50, 75, $e->getMessage());

        $imagick = new \Imagick();
        $imagick->newImage(500, 250, "SteelBlue2");
        $imagick->setImageFormat("png");
        $imagick->drawImage($draw);

        header("Content-Type: image/png");
        echo $imagick->getImageBlob();
    }
}

*/