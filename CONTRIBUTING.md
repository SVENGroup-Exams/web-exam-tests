# Updating The Coding Exam Tests

When making updates to this repository make sure to perform the following:

- Run your new test case/s against a sample solution of the coding exam.
- Update `.github/workflows/classroom.yml`
  - The action currently only copies [`TestCase.php`](./TestCase.php) and the contents of the [`/Feature`](./Feature/) directory into the `/tests` directory of the coding exam. If you're adding Unit tests or other tests outside of this file and directory, make sure you also configure the action to copy those over.
  - For every new test case, include a new step under the `autograde` job. These steps use [education/autograding-command-grader@v1](https://github.com/github-education-resources/autograding-command-grader) so read their documentation.
  - Update the "Report Scores" step under the `autograde` job. This step uses [classroom-resources/autograding-grading-reporter@v1](https://github.com/classroom-resources/autograding-grading-reporter). Read their documentation and apply the necessary updates to this step.
  