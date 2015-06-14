<?php

namespace ImagickDemo\Controller;

use Intahwebz\Request;
use ImagickDemo\Response\JsonResponse;
use ImagickDemo\Queue\ImagickTaskQueue;
use ImagickDemo\Helper\PageInfo;


/**
 * Class Image
 * @package ImagickDemo\Controller
 */
class Image {

    /**
     * @var PageInfo
     */
    private $pageInfo;
    
    function __construct(PageInfo $pageInfo)
    {
        $this->pageInfo = $pageInfo;
    }
    
    /**
     * @param $category
     * @param $example
     * @param $imageFunction
     * @param \ImagickDemo\Control $control
     * @param \ImagickDemo\Example $exampleController
     * @internal param array $customImageParams
     * @return JsonResponse
     */
    function getImageJobStatus(
        $category,
        $example,
        $imageFunction,
        \ImagickDemo\Control $control,
        \ImagickDemo\Example $exampleController
    ) {

        $data = [];
        $customImageParams = $exampleController->getCustomImageParams();
        
        $fullParams = $control->getFullParams($customImageParams);
        
        $filename = getImageCacheFilename($category, $example, $fullParams);
        $data['filename'] = $filename;
        $data['finished'] = false;
        $data['params'] = $fullParams;

        foreach (getKnownExtensions() as $extension) {
            if (file_exists($filename.'.'.$extension) == true) {
                $data['finished'] = true;
                break;
            }
        }

        return new JsonResponse($data);
    }


    /**
     * @param \Auryn\Injector $injector
     * @param $params
     * @return mixed
     * @throws \Exception
     */
    private function getImageResponseInternal(\Auryn\Injector $injector, $params) {
        $callables = [];
        
        if (false) {
            $logCallable = function ($imageFunction,
                                     $category,
                                     $example) use ($params) {

                if (file_exists("test.data.php") == false) {
                    file_put_contents("test.data.php", "<?php \n\n\$data = [];\n\n", FILE_APPEND);
                }

                $string = "\$data[] = [\n";
                $string .= var_export($imageFunction, true);
                $string .= ",\n";
                $string .= var_export($params, true);
                $string .= ",\n";
                $string .= "];\n\n";

                file_put_contents("test.data.php", $string, FILE_APPEND);
            };
            $callables[] = $logCallable;
        }
            
        $cacheImageFile = function ($imageFunction, 
                                    $category,
                                    $example,
                                    \Auryn\Injector $injector) use ($params) {
            $filename = getImageCacheFilename($category, $example, $params);
            $lowried = [];
            foreach($params as $key => $value) {
                $lowried[':'.$key] = $value;
            }

            return renderImageAsFileResponse($imageFunction, $filename, $injector, $lowried);
        };

        $getCachedImageResponse = function($category, $example) use ($params) {
            return getCachedImageResponse($category, $example, $params);
        };
        
        $processImageTask = function (Request $request,
                                      $imageFunction,
                                      ImagickTaskQueue $taskQueue,
                                      $category, $example) use ($params) {
            $debug = 'Unknown state';

            $job = $request->getVariable('job', false);
            if ($job === false) {
                if ($taskQueue->isActive() == false) {
                    //Queue isn't active - don't bother queueing a task
                    return null;
                }

                $task = \ImagickDemo\Queue\ImagickTask::create(
                    $category,
                    $example,
                    $imageFunction,
                    $params
                );

                $debug .= "task created.";

                $taskQueue->addTask($task);
            }

            if ($request->getVariable('noredirect') == true) {
                return new \ImagickDemo\Response\ErrorResponse(503, "image still processing $job is ".$job.$debug);
            }

            return redirectWaitingTask($request, intval($job));
        };

        
        
        $directImageCallable = function (
            $imageFunction,
            \Auryn\Injector $injector,$category,
            $example,
            \Auryn\Injector $injector) use ($params) 
        {
            $filename = getImageCacheFilename($category, $example, $params);
            
            return directImageFunction($filename, $imageFunction, $injector);
        };
        
        
        
        global $cacheImages;
        if ($cacheImages == false) {
            $callables[] = 'checkGetOriginalImage';
            $callables[] = $directImageCallable;//'directImageFunction';;
        }
        else {
            $callables[] = 'checkGetOriginalImage';
            $callables[] = $getCachedImageResponse;// //This also reads the image when generated by a task
            $callables[] = $processImageTask;
            $callables[] = $cacheImageFile;
            $callables[] = $directImageCallable;//'directImageFunction';
        }
        
        foreach ($callables as $callable) {
            $result = $injector->execute($callable);
            if ($result) {
                return $result;
            }
        }

        throw new \Exception("Failed to process image request.");
    }

    /**
     * @param \Auryn\Injector $injector
     * @param $customImageFunction
     * @param \ImagickDemo\Example $exampleController
     * @param \ImagickDemo\Control $control
     * @return mixed
     * @throws \Exception
     */
    function getCustomImageResponse(
        \Auryn\Injector $injector,
        $customImageFunction,
        \ImagickDemo\Example $exampleController,
        \ImagickDemo\Control $control
    ) {
        $injector->defineParam('imageFunction', $customImageFunction);
        $params = $control->getFullParams($exampleController->getCustomImageParams());
        $defaultCustomParams = array('customImage' => true);
        $params = array_merge($defaultCustomParams, $params);

        return $this->getImageResponseInternal($injector, $params);
    }

    /**
     * @param \Auryn\Injector $injector
     * @param \ImagickDemo\Control $control
     * @throws \Exception
     * @internal param Request $request
     * @return array|callable
     */
    function getImageResponse(\Auryn\Injector $injector, \ImagickDemo\Control $control) {
        $params = $control->getFullParams([]);

        return $this->getImageResponseInternal($injector, $params);
    }
}