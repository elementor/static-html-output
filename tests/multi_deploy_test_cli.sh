#!/bin/bash


for deployer in bitbucket ftp github gitlab netlify s3

do
  echo "$deployer" ;
  wp option update blogname "$deployer test"
  wp statichtmloutput options set selected_deployment_option "$deployer"
  wp statichtmloutput options set baseUrl $(wp statichtmloutput options get "baseUrl-$deployer")
  wp statichtmloutput deploy
done

exit

# Example usage to get each Destination URL printed after deploy
#
# (or just get from statichtmloutput options)
#
# function printArchiveInfo( $archive ) {
#     error_log( $archive->settings['baseUrl'] );
# }
#
# add_filter( 'statichtmloutput_post_deploy_trigger', 'printArchiveInfo' );
