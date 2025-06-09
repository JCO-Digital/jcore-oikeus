# JCORE Template module
A JCORE Template module.

# TODOs for new modules:

- Change the namespace + all occurences of the word template
- Start coding away

## Before deployment:

- Enable GH Actions
- Add a PAT token to the secrets of the repository to enable the Github Actions workflow. (the precise name is found in .github/workflows/push.yml)
  -  The PAT should have access to push to the repository at the very least.
- Add push protection to the main branch
- Setup a new package on packagist.org that tracks this repository