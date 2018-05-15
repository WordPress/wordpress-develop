def label = "mypod-${UUID.randomUUID().toString()}"
// Create pod template using desired image
podTemplate(label: label, containers: [
    containerTemplate(name: 'buildwordpress', image: 'shirishk/buildwp', ttyEnabled: true, command: 'cat')
  ]) {
    node(label) {
        // Kafka settings to capture jenkins logs
        wrap([$class: 'KafkaBuildWrapper', kafkaServers: 'http://ps-kafka-kafka.dev:9092', kafkaTopic: 'buildlogs', metadata: env.JOB_URL]) {
            try {
                // Git clone source code.
                stage('Clone sources') {
                    if (env.JOB_NAME == "build-wordpress-dev") {
                        checkout changelog: true, poll: true, scm: [
                            $class: 'GitSCM',
                            branches: [[name: "origin/${env.gitlabSourceBranch}"]],
                            doGenerateSubmoduleConfigurations: false,
                            // Pre-Build Merge your branch to 'develop'
                            extensions: [[$class: 'PreBuildMerge', options: [fastForwardMode: 'FF', mergeRemote: 'origin', mergeStrategy: 'default', mergeTarget: "${env.gitlabTargetBranch}"]]],
                            submoduleCfg: [],
                            userRemoteConfigs: [[name: 'origin', credentialsId: 'a167a167-d53c-43b4-923c-2da581f74bba', url: 'http://early-jaguar-gitlab-ce/root/wordpress-develop.git']]
                            ]
                        }
                    if (env.JOB_NAME == "build-wordpress-qa") {
                        git credentialsId: 'a167a167-d53c-43b4-923c-2da581f74bba', url: 'http://early-jaguar-gitlab-ce/root/wordpress-develop.git'
                    }
                }
                // Lunch Container
                stage('Environment Preparation for Wordpress') {
                    container('buildwordpress') {
                        // Install dependancies and build project
                        stage('Build project') {
                            sh "npm install"
                        }
                        // Run QUnit test cases
                        stage('Run Unit Tests') {
                            sh "grunt qunit:reports --force"
                        }
                        // Create tar.gz package
                        stage('Create Package') {
                            sh "mkdir wordpress" 
                            sh "cp -rf build/* wordpress/"
                            sh "tar -czvf Wordpress.tar.gz wordpress/"
                        }
                    }
                    if (env.JOB_NAME == "build-wordpress-qa") {
                        // Archive the package in Nexus repository.
                        stage('Add to Nexus artifacts') {
                            nexusArtifactUploader artifacts: [[artifactId: 'wp_artId1', classifier: 'Wordpress', file: 'Wordpress.tar.gz', type: 'tar.gz']], credentialsId: '9a50fa79-caca-4c1f-b65a-6f2165ade759', groupId: 'wp_gid', nexusUrl: 'docker.pure.synerzip.in', nexusVersion: 'nexus3', protocol: 'http', repository: 'wordpress', version: '1.0.0.'+env.BUILD_NUMBER
                        }
                        // Publish Unit Test Reports.
                        stage('Publish Qunit Reports') {
                            junit '_build/test-reports/*.xml'
                        }
                    }
                    if (env.JOB_NAME == "build-wordpress-dev") {
                        // Accept GitLab merge request on success.
                        stage('GitLab merge request'){
                            acceptGitLabMR mergeCommitMessage: 'LGTM'
                        }
                    }
                }
            } catch (e) {
                stage('Create Jira ticket on failure') {
                    withEnv(['JIRA_SITE=JIRA']) {
                        def testIssue = [fields: [ project: [key: 'WOR'],
                            summary: 'Issue Created from Jenkins Job - '+env.JOB_NAME+' Build # '+ env.BUILD_NUMBER,
                            description: e,
                            issuetype: [name: 'Bug']]]
                        response = jiraNewIssue issue: testIssue
                        echo response.successful.toString()
                        echo response.data.toString()
                    }
                    currentBuild.result = 'FAILURE'
                }
            }
        }
    }
}