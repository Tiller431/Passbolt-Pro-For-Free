<?php
use Cake\Routing\Router;
?>
<?= $this->element('header', ['title' => __('Choose your preferences.')]) ?>
<div class="panel main ">
    <!-- wizard steps -->
    <div class="panel left">
        <?= $this->element('navigation', ['selectedSection' => 'options']) ?>
    </div>
    <!-- main -->
    <?= $this->Form->create($optionsConfigurationForm); ?>
    <div class="panel middle">
        <div class="grid grid-responsive-12">
            <div class="row">
                <div class="col7">
                    <div class="row">
                        <div class="col12">
                            <h3><?= __('Options'); ?></h3>
                            <?= $this->Flash->render() ?>
                            <?php
                            echo $this->Form->input('full_base_url',
                                [
                                    'required' => 'required',
                                    'placeholder' => __('Full Base Url'),
                                    'label' => __('Full base url'),
                                    'class' => 'required fluid',
                                    'templates' => [
                                        'inputContainer' => '<div class="input text required">{{content}}<div class="message">' . __('This is the url where passbolt will be accessible. This url will be used for places where the passbolt url cannot be guessed automatically, such as links in emails. No trailing slash.') . '</div></div>',
                                    ]
                                ]
                            );
                            ?>
                            <div class="input text required">
                                <label for="PublicRegistration"><?= __('Allow public registration?'); ?></label>
                                <?php
                                echo $this->Form->select(
                                    'public_registration',
                                    ['1' => 'Yes', '0' => 'No'],
                                    ['default' => '0', 'class' => 'required fluid']
                                );
                                ?>
                                <div class="message"><?= __('Allowing public registration means that any visitor can create himself an account on your passbolt. Unless your instance of passbolt is not reachable by the outside world, it is usually a bad idea.') ?></div>
                            </div>
                            <div class="input text required">
                                <label for="ForceSsl"><?= __('Force SSL?'); ?></label>
                                <?php
                                echo $this->Form->select(
                                    'force_ssl',
                                    ['1' => 'Yes', '0' => 'No'],
                                    ['default' => $force_ssl, 'class' => 'required fluid']
                                );
                                ?>
                                <div class="message"><?= __('Forcing SSL means that passbolt will not accept connections coming from a non secure protocol. If Force SSL is active, your server has to be configured for HTTPS. It is highly recommended that you do so.') ?></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col5 last">
                </div>
            </div>
            <div class="row last">
                <div class="input-wrapper">
                    <a href="<?= Router::url($stepInfo['previous'], true); ?>" class="button cancel big"><?= __('Cancel'); ?></a>
                    <input type="submit" class="button primary next big" value="<?= __('Next'); ?>">
                </div>
            </div>
        </div>
    </div>
    <?= $this->Form->end(); ?>
</div>
