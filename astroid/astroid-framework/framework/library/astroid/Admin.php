<?php

/**
 * @package   Astroid Framework
 * @author    JoomDev https://www.joomdev.com
 * @copyright Copyright (C) 2009 - 2019 JoomDev.
 * @license https://www.gnu.org/licenses/gpl-2.0.html GNU/GPLv2 or Later
 */

namespace Astroid;

use Astroid\Component\Includer;

defined('_JEXEC') or die;

class Admin extends Helper\Client
{
    protected function save()
    {
        $this->checkAuth();
        $app = \JFactory::getApplication();

        $params = $app->input->post->get('params', array(), 'RAW');
        $template_id = $app->input->get('id', NULL, 'INT');
        $template_name = $app->input->get('template', NULL, 'RAW');

        $params = \json_encode($params);
        file_put_contents(JPATH_SITE . "/templates/{$template_name}/params" . '/' . $template_id . '.json', $params);

        Helper::refreshVersion();

        $this->response("saved");
    }

    protected function media()
    {
        $action = \JFactory::getApplication()->input->get('action', '', 'RAW');
        $func = Helper::classify($action);
        if (!method_exists(Helper\Media::class, $func)) {
            throw new \Exception("`{$func}` function not found in Astroid\\Helper\\Media");
        }
        $this->response(Helper\Media::$func());
    }

    protected function search()
    {
        $search = \JFactory::getApplication()->input->get('search', '', 'RAW');
        switch ($search) {
            case 'icon':
                $this->response(self::icons());
                break;
            default:
                throw new \Exception('Bad Request', 400);
                break;
        }
    }

    protected function googleFonts()
    {
        $this->format = 'html'; // Response Format
        $this->response(Helper\Font::getAllFonts());
    }

    protected function icons()
    {
        $return = ['success' => true];
        $return['results'] = Helper\Font::fontAwesomeIcons(true);
        return $return;
    }

    protected function manager()
    {
        $document = Framework::getDocument();

        Framework::getDebugger()->log('forms');
        $form = Framework::getForm();
        $form->loadOptions(JPATH_LIBRARIES . '/astroid/framework/options');
        $form->loadOptions(ASTROID_TEMPLATE_PATH . '/astroid/options');
        Framework::getDebugger()->log('forms');

        $this->format = 'html'; // Response Format
        $this->checkAndRedirect(); // Auth

        $app = \JFactory::getApplication();
        $id = $app->input->get('id', NULL, 'INT');
        $template = Framework::getTemplate($id);

        Framework::getDebugger()->log('langauges');
        Helper::loadLanguage('astroid');
        Helper::loadLanguage('tpl_' . ASTROID_TEMPLATE_NAME);
        Helper::loadLanguage(ASTROID_TEMPLATE_NAME);
        Helper::loadLanguage('mod_menu');
        Framework::getDebugger()->log('langauges');

        // scripts
        $scripts = ['vendor/jquery/jquery-3.2.1.min.js', 'vendor/jquery/jquery.cookie.js', 'vendor/bootstrap/js/popper.min.js', 'vendor/bootstrap/js/bootstrap.min.js', 'vendor/spectrum/spectrum.js', 'vendor/ace/1.3.3/ace.js', 'vendor/dropzone/dropzone.min.js', 'vendor/moment/moment.min.js', 'vendor/moment/moment-timezone.min.js', 'vendor/moment/moment-timezone-with-data-2012-2022.min.js', 'vendor/bootstrap/js/bootstrap-datetimepicker.min.js', 'vendor/bootstrap-slider/js/bootstrap-slider.min.js', 'vendor/angular/angular.min.js', 'vendor/angular/angular-animate.js', 'vendor/angular/sortable.min.js', 'vendor/angular/angular-legacy-sortable.js', 'js/parsley.min.js', 'js/notify.min.js', 'js/jquery.hotkeys.js', 'js/jquery.nicescroll.min.js', 'vendor/semantic-ui/components/transition.min.js', 'vendor/semantic-ui/components/api.min.js', 'vendor/semantic-ui/components/dropdown.min.js', 'js/astroid.min.js'];
        $document->addScript($scripts, 'body');
        $document->addScriptDeclaration('moment.tz.setDefault(\'' . \JFactory::getConfig()->get('offset') . '\');', 'body');

        // styles
        $stylesheets = ['https://fonts.googleapis.com/css?family=Nunito:300,400,600', 'css/astroid-framework.css', 'css/admin.css', 'css/animate.min.css', 'vendor/semantic-ui/components/icon.min.css', 'vendor/semantic-ui/components/transition.min.css', 'vendor/semantic-ui/components/dropdown.min.css'];
        $document->addStyleSheet($stylesheets);

        Helper::triggerEvent('onBeforeAstroidAdminRender', [&$template]);

        Framework::getDebugger()->log('manager');
        $layout = new \JLayoutFile('manager.index', ASTROID_LAYOUTS);
        $html = $layout->render();
        Framework::getDebugger()->log('manager');
        $this->response(Includer::run($html));
    }

    protected function auditor()
    {
        $this->format = 'html'; // Response Format
        $this->checkAndRedirect(); // Auth

        Helper::loadLanguage('astroid');

        $layout = new \JLayoutFile('framework.auditor', ASTROID_LAYOUTS);
        $this->response($layout->render());
    }

    protected function audit()
    {
        $template = \JFactory::getApplication()->input->get->get('template', '', 'RAW');
        $this->response(Auditor::audit($template));
    }

    protected function migrate()
    {
        $template = \JFactory::getApplication()->input->get->get('template', '', 'RAW');
        $this->response(Auditor::migrate($template));
    }

    protected function clearCache()
    {
        $template = \JFactory::getApplication()->input->get->get('template', '', 'RAW');
        Helper::clearCacheByTemplate($template);
        $this->response(['message' => \JText::_('TPL_ASTROID_SYSTEM_MESSAGES_CACHE')]);
    }

    protected function clearJoomlaCache()
    {
        Helper::clearJoomlaCache();
        $this->response(['message' => \JText::_('TPL_ASTROID_SYSTEM_MESSAGES_JCACHE')]);
    }

    public function addTemplateLabels()
    {
        $app = \JFactory::getApplication();
        $body = $app->getBody();
        $astroid_templates = Helper\Template::getAstroidTemplates();
        $body = preg_replace_callback('/(<a\s[^>]*href=")([^"]*)("[^>]*>)(.*)(<\/a>)/siU', function ($matches) use ($astroid_templates) {
            $html = $matches[0];
            if (strpos($matches[2], 'task=style.edit')) {
                $uri = new \JUri($matches[2]);
                $id = (int) $uri->getVar('id');
                if ($id && in_array($uri->getVar('option'), array('com_templates')) && (in_array($id, $astroid_templates))) {
                    $html = $matches[1] . $uri . $matches[3] . $matches[4] . $matches[5];
                    $html .= ' <span class="label" style="background: rgba(0, 0, 0, 0) linear-gradient(to right, #8E2DE2, #4A00E0) repeat scroll 0 0; color:#fff;padding-left: 10px;padding-right: 10px;margin-left: 5px;border-radius: 30px;box-shadow: 0px 0px 20px rgba(0, 0, 0, 0.20);display: inline-block;">Astroid</span>';
                }
            }
            return $html;
        }, $body);
        $app->setBody($body);
    }
}
