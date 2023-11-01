<?php
namespace williamgall\lambase\Model;

class ClassInfoModel extends BaseModel
{
    public $tableName = 'class_requirement';
    public $pkId = 'id';
    public $useAdapter;
    private $log;
    private $moduleIssues;
    private $totalIssues;
    private $countOnly;

    public function __construct($adapter=NULL)
    {
        if ($adapter == NULL)
        {
            $this->useAdapter = 'eap';
        }else{
            $this->useAdapter = $adapter;
        }
        parent::__construct();
    }

    public function getClassInfoBig()
    {
        return $this->rglob("./module/*.php");
        foreach (glob('./module/DevDash/src/Model/*.php') as $file)
        {
            $log = "";
            require_once $file;

            // get the file name of the current file without the extension
            // which is essentially the class name
            $class = basename($file, '.php');

            $log .= $class.".*.php<br>";

            if (class_exists($class))
            {
                $obj = new $class;
                foreach(get_class_methods($obj) as $method)
                {
                    $log .= " - Method:". $method."<br>";
                }
            }
            $log .= "<br>";
        }
    }

    /** Recursive glob
     *
     * Tested
     * @param $pattern
     * @param $flags
     * @return array|false
     */
    public function rglob($pattern, $flags = 0) {
        $files = glob($pattern, $flags);
        foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
            $files = array_merge(
                [],
                ...[$files, $this->rglob($dir . "/" . basename($pattern), $flags)]
            );
        }
        return $files;
    }

    public function getControllers($module=NULL)
    {
        if ($module === NULL) {
            return $this->rglob("./module/*Controller.php");
        }else{
            return $this->rglob("./module/".$module."/*Controller.php");
        }
    }
    public function getModels($module=NULL)
    {
        if ($module === NULL) {
            return $this->rglob("./module/*/src/Model/*.php");
        }else{
            return $this->rglob("./module/".$module."/src/Model/*.php");
        }
    }

    public function getModelsNotNamedModelForModule($module)
    {
        $returnArray = [];
        $classes = $this->rglob("./module/".$module."/src/Model/*.php");
        foreach ($classes as $class)
        {
            if (! str_ends_with($class,"Model.php"))
            {
                $returnArray[] = $class;
            }
        }
        return $returnArray;
    }

    public function getModelClassesForModule($module)
    {
        return $this->rglob("./module/".$module."/src/Model/*.php");
    }
    public function getFormsNotNamedFormForModule($module)
    {
        $returnArray = [];
        $classes = $this->rglob("./module/".$module."/src/Form/*.php");
        foreach ($classes as $class)
        {
            if (! str_ends_with($class,"Form.php"))
            {
                $returnArray[] = $class;
            }
        }
        return $returnArray;
    }
    public function getMethodsForClasses(bool|array $files)
    {
        $log = "";
        foreach ($files as $file)
        {
            require_once $file;
//            echo $file;

            // get the file name of the current file without the extension
            // which is essentially the class name
            $className = basename($file, '.php');
            $log .= $className.".php<br>";
            if (class_exists("\\Caf\\Controller\\".$className))
            {
                $class = "\\Caf\\Controller\\".$className;
                $log .= "Class exists ".$className;
                $obj = new $class;
                foreach(get_class_methods($obj) as $method)
                {
                    $log .= " - Method:". $method."<br>";
                }
            }
            $log .= "<br>";
        }
        return $log;
    }

    public function getMethodsFromClass($module,$type,$class)
    {
        $class = substr($class,0,strpos($class,"."));
        $class = new \ReflectionClass("\\".$module."\\".$type."\\".$class);
        $methods = $class->getMethods();
        return $methods;
    }

    /**
     * @param object|string $class
     * @param $search
     * @return false|string
     * @throws \ReflectionException
     */
    public function getDocCommentFormMethod(object|string $class, $search)
    {
//        $this->debug($class);
//        $class = "\\Application\\Model\\DisplayModel";
        $class = new \ReflectionClass($class);
        $methods = $class->getMethods();
        foreach ($methods as $method)
        {
            if ($method->name == $search) {
                return $method->getDocComment();
            }

        }
        return "Not Found";
    }
    public function isTested($class, $method)
    {
//        $this->debug($class);
        $docComment = $this->getDocCommentFormMethod($class, $method);
        if (str_contains($docComment,'Tested') || str_contains($docComment,'mantest'))
        {
            return TRUE;
        }
        return FALSE;
    }

    /**
     * @return array
     */
    public function getModules()
    {
        $returnArray = [];
        $modules = glob("./module/*",GLOB_ONLYDIR);
        foreach ($modules as $module)
        {
            $returnArray[] = substr($module,9);
        }
        return $returnArray;
    }


    /**
     * @param string $module
     * @return array
     */
    public function getModelsForModule(string $module)
    {
        $returnArray = [];
        $models = glob("./module/".$module."/src/Model/*.php");
        foreach ($models as $model)
        {
            $returnArray[] = basename($model);
        }
        return $returnArray;
    }

    public function checkAllModules($moduleList=NULL,int $countOnly=0, int $reportTesting=0)
    {
        $this->countOnly = $countOnly;
        $this->log = "";
        $this->totalIssues=0;
        if ($moduleList === NULL)
        {
            $modules = $this->getModules();
        }else {
            $modules = $moduleList;
        }
        foreach ($modules as $module)
        {
            $this->moduleIssues=0;
            /**
             * Controllers
             */
            $this->checkControllersForModule($module,$countOnly,$reportTesting);

            /**
             * Forms
             */
            $this->checkFormsForModule($module, $countOnly,$reportTesting);

            /**
             * Models
             */
            $this->checkModelsNotNamedModel($module,$countOnly,$reportTesting);
            $this->checkModelsForModule($module,$countOnly,$reportTesting);


            $this->log .= $this->moduleIssues." Issues with ".$module."<br>";
            $this->totalIssues = $this->totalIssues + $this->moduleIssues;

        }
        $this->log .= $this->totalIssues." Issues in Project";
        return $this->log;
    }

    public function hasProperty($classFile,$propery)
    {
        $callableClass = $this->getCallableClassFromFile($classFile);
        $class = new \ReflectionClass($callableClass);
        return $class->hasProperty($propery);
    }

    public function checkClass($module, $class, $type, $log,$m)
    {
        $callableClass = $this->getCallableClassFromFile($class);
        $className = $this->getClassNameFromFile($class);
        $class = $this->createReflectionClass($callableClass);
        $requirements = $this->getRequirementsForType($type);
        foreach ($requirements as $requirement)
        {
            if ($requirement['param'] == 'parameter')
            {
                if ($requirement['not'])
                {
                    if ($class->hasProperty($requirement['value'])) {
                        if (!$this->countOnly) {
                            $this->log .= $className . "  has property " . $requirement['value'] . "<br>";
                        }
                        $this->moduleIssues++;
                    }
                }else {
                    if (!$class->hasProperty($requirement['value'])) {
                        if (!$this->countOnly) {
                            $this->log .= $className . " doesn't have property " . $requirement['value'] . "<br>";
                        }
                        $this->moduleIssues++;
                    }

                }
            }
        }
    }

    private function createReflectionClass($callableClass)
    {
        $class = new \ReflectionClass($callableClass);
        return $class;
    }
    public function todo()
    {
        // xx find test file for class
        //xx test for properties
        // ?? inNamespace not needed becuase we have their namespace unless this will check its declared
        // returnType
        // # lines
        // ?? get parent class
        // xx ensure all models extends base
        // all controllers extend base
    }

    private function getRequirementsForType($type)
    {
        return $this->fetchAll("SELECT * FROM class_requirement WHERE type = ? AND active",$type);
    }

    /**
     * Tested
     * @param $class
     * @return string
     */
    private function getCallableClassFromFile($class)
    {
        $parts = explode("/",$class);
        $className = substr($parts[5],0,strpos($parts[5],"."));
        $callableClass = "\\".$parts[2]."\\".$parts[4]."\\".$className;
        return $callableClass;
    }

    /**
     * Tested
     * @param $class
     * @return string
     */
    private function getClassNameFromFile($class)
    {
        $parts = explode("/",$class);
        $className = substr($parts[5],0,strpos($parts[5],"."));
        return $className;
    }

    /**
     * @param string $classFile
     * @param string $extends
     * @return void
     */
    public function extendsClass(string $classFile, string $extends)
    {
        $callableClass = $this->getCallableClassFromFile($classFile);
        $className = $this->getClassNameFromFile($classFile);
//        error_log($extends);
        if ($callableClass == $extends)
        {
            return;
        }
        $class = $this->createReflectionClass($callableClass);
        // TODO check for exception in doc ? maybe don't beed
        $result = $class->isSubclassOf($extends);
        if (! $result)
        {
            if (! $this->countOnly) {
                $this->log .= $className . " doesn't extend " . $extends . "<br>";
            }
            $this->moduleIssues++;
        }
    }

    // './module/DevDash/src/Controller/BaseController.php'
    private function checkControllersForModule(mixed $module, int $countOnly, int $reportTesting)
    {
        $controllers = $this->getControllers($module);
//            $this->debug($controllers);
        foreach ($controllers as $controller)
        {
//            $this->debug($controller);
            $this->checkClass($module,$controller,'Controller',$this->log,$this->moduleIssues);
            $this->extendsClass($controller,"\\DevDash\\Controller\\BaseController");
            $this->hasTestController($controller, $countOnly);
            $this->checkMethodsForController($controller, $countOnly);
        }
    }

    private function checkFormsForModule(mixed $module, int $countOnly, int $reportTesting)
    {
        $forms = $this->getFormsNotNamedFormForModule($module);
        if (!empty($forms))
        {
            foreach ($forms as $form)
            {
                if ($countOnly != 1)
                {
                    $this->log .= $module . " " . $form . " is not named form<br>";
                }
                $this->moduleIssues++;
            }
        }
    }

    private function checkModelsNotNamedModel(mixed $module, int $countOnly, int $reportTesting)
    {
        $models = $this->getModelsNotNamedModelForModule($module);
        if (!empty($models))
        {
            foreach ($models as $model)
            {
                $this->checkClass($module,$model,'Model',"","");
                $this->extendsClass($model,"\\DevDash\\Model\\Base");
                if (! $countOnly) {
                    $this->log .= $module . " " . $model . " is not named model<br>";
                }
                $this->moduleIssues++;
            }
        }
    }

    private function checkModelsForModule(mixed $module, int $countOnly, int $reportTesting)
    {
        $models = $this->getModelsForModule($module);
        if (! empty($models))
        {
            foreach ($models as $model)
            {
                $methods = $this->getMethodsFromClass($module,'Model',$model);
//                    $this->debug($methods);
                if (! empty($methods))
                {
                    foreach ($methods as $method)
                    {
                        if ($method->name != "__construct")
                        {
                            if ($this->methodIsInherited($method))
                            {

                            }else {
                                $modelName = substr($model, 0, strpos($model, "."));
                                $tested = $this->isTested("\\" . $module . "\\Model\\" . $modelName, $method->name);
                                if (!$tested) {
                                    if (!$countOnly) {
                                        $this->log .= $module . " " . $modelName . "::" . $method->name . " is not tested<br>";
                                    }
                                    $this->moduleIssues++;
                                }
                            }
                        }
                    }
                }


            }
        }else{
            if ($countOnly != 1) {
                $this->log .= "No Models for Module " . $module . "<br>";
            }
        }
        /**
         * Check For Test File
         */
        $modelFiles = $this->getModels($module);
        foreach ($modelFiles as $modelFile)
        {
            $this->hasTestModel($modelFile, $countOnly);
        }
    }

    private function hasTestController(mixed $controller,$countOnly)
    {
        $module = $this->getModuleFromDotPathFileString($controller);
        $class = $this->getClassNameFromFile($controller);
        $file = "./module/".$module."/test/Controller/".$class."Test.php";
        if (file_exists($file))
        {
            return true;
        }
        if (!$countOnly)
        {
            $this->log .= "No Test Controller ".$file."<br>";
        }
        $this->moduleIssues++;
        return false;
    }

    /**
     * Tested
     * @param mixed $classFile
     * @return string
     */
    private function getModuleFromDotPathFileString(mixed $classFile): string
    {
        $parts = explode("/",$classFile);
        return $parts[2];
    }

    private function hasTestModel(mixed $model, int $countOnly)
    {
        $module = $this->getModuleFromDotPathFileString($model);
        $modelName = $this->getClassNameFromFile($model);
        $file = "./module/".$module."/test/Model/".$modelName."Test.php";
        if (file_exists($file))
        {
            return true;
        }
        if (!$countOnly)
        {
            $this->log .= "No Test Model ".$file."<br>";
        }
        $this->moduleIssues++;
        return false;
    }

    /**
     * Tested
     * @param \ReflectionMethod $method
     * @return bool
     */
    private function methodIsInherited(\ReflectionMethod $method): bool
    {
//        $this->debug($method->getDocComment());
        if ($method->class == 'DevDash\Model\Base')
        {
            return true;
        }else{
            return false;
        }
    }

    private function getFileFromCallableClass(string $class)
    {
        $parts = explode("\\",$class);
//        $this->debug($parts);
        return "./module/".$parts[0]."/src/".$parts[1]."/".$parts[2].".php";
    }

    private function checkMethodsForController(mixed $controller, int $countOnly)
    {
        $callableClass = $this->getCallableClassFromFile($controller);
        $class = $this->createReflectionClass($callableClass);
        $className = $class->name;
        foreach ($class->getMethods() as $method)
        {
            if ($method->name != '__construct' && $method->class != "DevDash\Controller\BaseController" && $method->class != "Laminas\Mvc\Controller\AbstractActionController" && $method->class != "Laminas\Mvc\Controller\AbstractController")
            {
                $comment = $method->getDocComment();
                if (!$comment)
                {
                    if (!$countOnly)
                    {
                        $this->log .= "No DocComment for method " . $class->name . " " . $method->name . "<br>";
                    }
                    $this->moduleIssues++;
                }else {
                    if (!str_contains($comment, "Tested") && !str_contains($comment, "mantest")) {
                        if (!$countOnly) {
                            $this->log .= "No test for method " . $class->name . " " . $method->name . "<br>";
                        }
                        $this->moduleIssues++;
                    }
                }
            }
//            $this->debug($class->name." ".$method->name);
        }
    }
}