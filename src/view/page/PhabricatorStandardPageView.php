<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class PhabricatorStandardPageView extends AphrontPageView {

  private $baseURI;
  private $applicationName;
  private $tabs = array();
  private $selectedTab;
  private $glyph;
  private $bodyContent;
  private $request;
  private $isAdminInterface;
  private $showChrome = true;
  private $isFrameable = false;
  private $disableConsole;
  private $searchDefaultScope;
  private $pageObjects = array();

  public function setIsAdminInterface($is_admin_interface) {
    $this->isAdminInterface = $is_admin_interface;
    return $this;
  }

  public function setIsLoggedOut($is_logged_out) {
    if ($is_logged_out) {
      $this->tabs = array_merge($this->tabs, array(
        'login' => array(
          'name' => 'Login',
          'href' => '/login/'
        )
      ));
    }
    return $this;
  }

  public function getIsAdminInterface() {
    return $this->isAdminInterface;
  }

  public function setRequest($request) {
    $this->request = $request;
    return $this;
  }

  public function getRequest() {
    return $this->request;
  }

  public function setApplicationName($application_name) {
    $this->applicationName = $application_name;
    return $this;
  }

  public function setFrameable($frameable) {
    $this->isFrameable = $frameable;
    return $this;
  }

  public function setDisableConsole($disable) {
    $this->disableConsole = $disable;
    return $this;
  }

  public function getApplicationName() {
    return $this->applicationName;
  }

  public function setBaseURI($base_uri) {
    $this->baseURI = $base_uri;
    return $this;
  }

  public function getBaseURI() {
    return $this->baseURI;
  }

  public function setTabs(array $tabs, $selected_tab) {
    $this->tabs = $tabs;
    $this->selectedTab = $selected_tab;
    return $this;
  }

  public function setShowChrome($show_chrome) {
    $this->showChrome = $show_chrome;
    return $this;
  }

  public function getShowChrome() {
    return $this->showChrome;
  }

  public function setSearchDefaultScope($search_default_scope) {
    $this->searchDefaultScope = $search_default_scope;
    return $this;
  }

  public function getSearchDefaultScope() {
    return $this->searchDefaultScope;
  }

  public function appendPageObjects(array $objs) {
    foreach ($objs as $obj) {
      $this->pageObjects[] = $obj;
    }
  }

  public function getTitle() {
    $use_glyph = true;
    $request = $this->getRequest();
    if ($request) {
      $user = $request->getUser();
      if ($user && $user->loadPreferences()->getPreference(
            PhabricatorUserPreferences::PREFERENCE_TITLES) !== 'glyph') {
        $use_glyph = false;
      }
    }

    return ($use_glyph ?
            $this->getGlyph() : '['.$this->getApplicationName().']').
      ' '.parent::getTitle();
  }


  protected function willRenderPage() {

    if (!$this->getRequest()) {
      throw new Exception(
        "You must set the Request to render a PhabricatorStandardPageView.");
    }

    $console = $this->getConsole();

    require_celerity_resource('phabricator-core-css');
    require_celerity_resource('phabricator-core-buttons-css');
    require_celerity_resource('phabricator-standard-page-view');
    if (PhabricatorEnv::getEnvConfig('notification.enabled')) {
      require_celerity_resource('phabricator-notification-css');
    }

    $current_token = null;
    $request = $this->getRequest();
    if ($request) {
      $user = $request->getUser();
      if ($user) {
        $current_token = $user->getCSRFToken();
      }
    }

    Javelin::initBehavior('workflow', array());
    Javelin::initBehavior(
      'refresh-csrf',
      array(
        'tokenName' => AphrontRequest::getCSRFTokenName(),
        'header'    => AphrontRequest::getCSRFHeaderName(),
        'current'   => $current_token,
      ));

    $pref_shortcut = PhabricatorUserPreferences::PREFERENCE_SEARCH_SHORTCUT;
    if ($user) {
      $shortcut = $user->loadPreferences()->getPreference($pref_shortcut, 1);
    } else {
      $shortcut = 1;
    }
    Javelin::initBehavior(
      'phabricator-keyboard-shortcuts',
      array(
        'helpURI' => '/help/keyboardshortcut/',
        'search_shortcut' => $shortcut,
      ));

    if ($console) {
      require_celerity_resource('aphront-dark-console-css');
      Javelin::initBehavior(
        'dark-console',
        array(
          'uri'         => '/~/',
          'request_uri' => $request ? (string) $request->getRequestURI() : '/',
        ));

      // Change this to initBehavior when there is some behavior to initialize
      require_celerity_resource('javelin-behavior-error-log');
    }

    $this->bodyContent = $this->renderChildren();
  }


  protected function getHead() {

    $framebust = null;
    if (!$this->isFrameable) {
      $framebust = '(top != self) && top.location.replace(self.location.href);';
    }

    $response = CelerityAPI::getStaticResourceResponse();

    $monospaced = PhabricatorEnv::getEnvConfig('style.monospace');

    $request = $this->getRequest();
    if ($request) {
      $user = $request->getUser();
      if ($user) {
        $monospaced = nonempty(
          $user->loadPreferences()->getPreference(
            PhabricatorUserPreferences::PREFERENCE_MONOSPACED),
          $monospaced);
      }
    }

    $viewport_tag = null;
    if (PhabricatorEnv::getEnvConfig('preview.viewport-meta-tag')) {
      $viewport_tag = phutil_render_tag(
        'meta',
        array(
          'name' => 'viewport',
          'content' => 'width=device-width, initial-scale=1, maximum-scale=1',
        ));
    }

    $head =
      $viewport_tag.
      '<script type="text/javascript">'.
        $framebust.
        'window.__DEV__=1;'.
      '</script>'.
      $response->renderResourcesOfType('css').
      '<style type="text/css">'.
        '.PhabricatorMonospaced { font: '.$monospaced.'; }'.
      '</style>'.
      $response->renderSingleResource('javelin-magical-init');

    return $head;
  }

  public function setGlyph($glyph) {
    $this->glyph = $glyph;
    return $this;
  }

  public function getGlyph() {
    return $this->glyph;
  }

  protected function willSendResponse($response) {
    $console = $this->getRequest()->getApplicationConfiguration()->getConsole();
    if ($console) {
      $response = str_replace(
        '<darkconsole />',
        $console->render($this->getRequest()),
        $response);
    }
    return $response;
  }

  protected function getBody() {
    $console = $this->getConsole();

    $tabs = array();
    foreach ($this->tabs as $name => $tab) {
      $tab_markup = phutil_render_tag(
        'a',
        array(
          'href'  => idx($tab, 'href'),
        ),
        phutil_escape_html(idx($tab, 'name')));
      $tab_markup = phutil_render_tag(
        'td',
        array(
          'class' => ($name == $this->selectedTab)
            ? 'phabricator-selected-tab'
            : null,
        ),
        $tab_markup);
      $tabs[] = $tab_markup;
    }
    $tabs = implode('', $tabs);

    $login_stuff = null;
    $request = $this->getRequest();
    $user = null;
    if ($request) {
      $user = $request->getUser();
      // NOTE: user may not be set here if we caught an exception early
      // in the execution workflow.
      if ($user && $user->getPHID()) {
        $login_stuff =
          phutil_render_tag(
            'a',
            array(
              'href' => '/p/'.$user->getUsername().'/',
            ),
            phutil_escape_html($user->getUsername())).
          ' &middot; '.
          '<a href="/settings/">Settings</a>'.
          ' &middot; '.
          phabricator_render_form(
            $user,
            array(
              'action' => '/search/',
              'method' => 'post',
              'style'  => 'display: inline',
            ),
            '<input type="text" name="query" id="standard-search-box" />'.
            ' in '.
            AphrontFormSelectControl::renderSelectTag(
              $this->getSearchDefaultScope(),
              PhabricatorSearchScope::getScopeOptions(),
              array(
                'name' => 'scope',
              )).
            ' '.
            '<button>Search</button>');
      }
    }

    $foot_links = array();

    $version = PhabricatorEnv::getEnvConfig('phabricator.version');
    $foot_links[] = phutil_escape_html('Phabricator '.$version);

    $foot_links[] =
      '<a href="https://secure.phabricator.com/maniphest/task/create/">'.
        'Report a Bug'.
      '</a>';

    if (PhabricatorEnv::getEnvConfig('darkconsole.enabled') &&
       !PhabricatorEnv::getEnvConfig('darkconsole.always-on')) {
      if ($console) {
        $link = javelin_render_tag(
          'a',
          array(
            'href' => '/~/',
            'sigil' => 'workflow',
          ),
          'Disable DarkConsole');
      } else {
        $link = javelin_render_tag(
          'a',
          array(
            'href' => '/~/',
            'sigil' => 'workflow',
          ),
          'Enable DarkConsole');
      }
      $foot_links[] = $link;
    }

    if ($user && $user->getPHID()) {
      // This ends up very early in tab order at the top of the page and there's
      // a bunch of junk up there anyway, just shove it down here.
      $foot_links[] = phabricator_render_form(
        $user,
        array(
          'action' => '/logout/',
          'method' => 'post',
          'style'  => 'display: inline',
        ),
        '<button class="link">Logout</button>');
    }

    $foot_links = implode(' &middot; ', $foot_links);

    $admin_class = null;
    if ($this->getIsAdminInterface()) {
      $admin_class = 'phabricator-admin-page-view';
    }

    $custom_logo = null;
    $with_custom = null;
    $custom_conf = PhabricatorEnv::getEnvConfig('phabricator.custom.logo');
    if ($custom_conf) {
      $with_custom = 'phabricator-logo-with-custom';
      $custom_logo = phutil_render_tag(
        'a',
        array(
          'class' => 'logo-custom',
          'href' => $custom_conf,
        ),
        ' ');
    }

    $notification_indicator = '';
    $notification_dropdown = '';
    $notification_container = '';

    if (PhabricatorEnv::getEnvConfig('notification.enabled') &&
      $user &&
      $user->isLoggedIn()) {

      $aphlict_object_id = 'aphlictswfobject';

      $client_uri = PhabricatorEnv::getEnvConfig('notification.client-uri');
      $client_uri = new PhutilURI($client_uri);
      if ($client_uri->getDomain() == 'localhost') {
        $this_host = $this->getRequest()->getHost();
        $this_host = new PhutilURI('http://'.$this_host.'/');
        $client_uri->setDomain($this_host->getDomain());
      }

      $enable_debug = PhabricatorEnv::getEnvConfig('notification.debug');

      Javelin::initBehavior(
        'aphlict-listen',
        array(
          'id'           => $aphlict_object_id,
          'server'       => $client_uri->getDomain(),
          'port'         => $client_uri->getPort(),
          'debug'        => $enable_debug,
          'pageObjects'  => array_fill_keys($this->pageObjects, true),
        ));

      Javelin::initBehavior('aphlict-dropdown', array());

      $notification_count = id(new PhabricatorFeedStoryNotification())
        ->countUnread($user);

      $indicator_classes = array(
        'phabricator-notification-indicator',
      );
      if ($notification_count) {
        $indicator_classes[] = 'phabricator-notification-indicator-unread';
      }

      $notification_indicator = javelin_render_tag(
        'div',
        array(
          'id'    => 'phabricator-notification-indicator',
          'class' => implode(' ', $indicator_classes),
        ),
        $notification_count);

      $notification_indicator = javelin_render_tag(
        'div',
        array(
          'id'    => 'phabricator-notification-menu',
          'class' => 'phabricator-icon-menu icon-menu-notifications',
          'sigil' => 'aphlict-indicator',
        ),
        $notification_indicator);

      $notification_indicator = javelin_render_tag(
        'td',
        array(
          'class' => 'phabricator-icon-menu-cell',
        ),
        $notification_indicator);

      $notification_container =
        '<div id="aphlictswf-container" style="height:0px; width:0px;">'.
        '</div>';
      $notification_dropdown =
        javelin_render_tag(
          'div',
          array(
            'sigil' => 'aphlict-dropdown',
            'id'    => 'phabricator-notification-dropdown',
            'style' => 'display: none',
          ),
          '');
    }

    $header_chrome = null;
    $footer_chrome = null;
    if ($this->getShowChrome()) {
      $header_chrome =
        '<table class="phabricator-standard-header">'.
          '<tr>'.
            '<td class="phabricator-logo '.$with_custom.'">'.
              $custom_logo.
              '<a class="logo-standard" href="/"> </a>'.
            '</td>'.
            $notification_indicator.
            '<td>'.
              '<table class="phabricator-primary-navigation">'.
                '<tr>'.
                  '<th>'.
                    phutil_render_tag(
                      'a',
                      array(
                        'href'  => $this->getBaseURI(),
                        'class' => 'phabricator-head-appname',
                      ),
                      phutil_escape_html($this->getApplicationName())).
                  '</th>'.
                  $tabs.
                '</tr>'.
              '</table>'.
            '</td>'.
            '<td class="phabricator-login-details">'.
              $login_stuff.
            '</td>'.
          '</tr>'.
        '</table>'.
        $notification_dropdown.
        $notification_container;
      $footer_chrome =
        '<div class="phabricator-page-foot">'.
          $foot_links.
        '</div>';
    }

    $developer_warning = null;
    if (PhabricatorEnv::getEnvConfig('phabricator.show-error-callout') &&
        DarkConsoleErrorLogPluginAPI::getErrors()) {
      $developer_warning =
        '<div class="aphront-developer-error-callout">'.
          'This page raised PHP errors. Find them in DarkConsole '.
          'or the error log.'.
        '</div>';
    }

    Javelin::initBehavior('device', array('id' => 'base-page'));
    $agent = idx($_SERVER, 'HTTP_USER_AGENT');

    // Try to guess the device resolution based on UA strings to avoid a flash
    // of incorrectly-styled content.
    $device_guess = 'device-desktop';
    if (preg_match('/iPhone|iPod/', $agent)) {
      $device_guess = 'device-phone';
    } else if (preg_match('/iPad/', $agent)) {
      $device_guess = 'device-tablet';
    }

    $classes = array(
      'phabricator-standard-page',
      $admin_class,
      $device_guess,
    );
    $classes = implode(' ', $classes);

    return
      ($console ? '<darkconsole />' : null).
      $developer_warning.
      phutil_render_tag(
        'div',
        array(
          'id' => 'base-page',
          'class' => $classes,
        ),
        $header_chrome.
        $this->bodyContent.
        '<div style="clear: both;"></div>').
      $footer_chrome;
  }

  protected function getTail() {
    $response = CelerityAPI::getStaticResourceResponse();
    return
      $response->renderResourcesOfType('js').
      $response->renderHTMLFooter();
  }

  protected function getBodyClasses() {
    $classes = array();

    if (!$this->getShowChrome()) {
      $classes[] = 'phabricator-chromeless-page';
    }

    return implode(' ', $classes);
  }

  private function getConsole() {
    if ($this->disableConsole) {
      return null;
    }
    return $this->getRequest()->getApplicationConfiguration()->getConsole();
  }
}
