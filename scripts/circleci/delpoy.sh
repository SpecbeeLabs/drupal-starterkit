#!/usr/bin/env bash
set -e

RESET='\033[0m';
STATUS='\033[1;92m';

whoami

echo -e "${STATUS}>> Switching to project docroot.${RESET}"
cd $DOCROOT

echo -e "${STATUS}>> Pulling down the latest code.${RESET}"
git pull origin $BRANCH

echo -e "${STATUS}>> Building artifact.${RESET}"
./vendor/bin/robo build:artifact

echo -e "${STATUS}>> Deployment complete.${RESET}"
