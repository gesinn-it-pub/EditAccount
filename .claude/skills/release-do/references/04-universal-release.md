**Procedure — release:do**

1.  Never rely on `gh auth login` / `~/.config/gh/hosts.yml` for the
    `gh` calls below — that file holds a single global login that can
    silently drift from the identity this repo actually needs. Instead,
    every step in this procedure that invokes `gh` resolves the token
    live from the environment and passes it inline, in the **same**
    shell invocation as the `gh` call — a variable set in one tool call
    does not survive into a later, separate tool call, so the resolution
    must be repeated at each `gh` call site rather than hoisted into a
    one-time preflight step:

    ``` shell
    gh_user=$(git config user.name)
    env_var="GH_TOKEN_$(echo "$gh_user" | tr '[:lower:]-' '[:upper:]_')"
    token="${!env_var}"
    if [ -z "$token" ]; then
      echo "Missing $env_var for gh user '$gh_user' — set it before releasing." >&2
      exit 1
    fi
    GH_TOKEN="$token" gh ...
    ```

2.  Confirm the target branch — the tag must be created from the correct
    branch:

    - Run `git branch --show-current`.

    - For a release on `main` (normal or MAJOR): stay on `main`.

    - For a backport release on an older major (e.g. `2.4.1` while `3.x`
      is on `main`): check out the maintenance branch first
      (`git checkout 2.x`). All remaining steps — including the tag and
      GitHub release — execute on that branch.

3.  Determine the new version number from commits since the last tag
    using SemVer rules:

    - Any breaking change (`!` or `BREAKING CHANGE`) → MAJOR

    - Any `feat` commit → MINOR

    - Only `fix`, `deps`, `refactor`, `docs` commits → PATCH

4.  If this is a **MAJOR** bump (e.g. `2.x → 3.0.0`): create a
    maintenance branch for the outgoing major **before** making any
    other changes:

    ``` console
    git checkout -b N.x          # e.g. git checkout -b 2.x  (snapshot of current main)
    git push origin N.x
    git checkout main            # tag 3.0.0 will be set from main
    ```

5.  Identify the version file for this project. Common locations:

    - `package.json` (Node.js)

    - `extension.json` / `composer.json` (MediaWiki extension)

    - `composer.json` (PHP library)

    - If unclear, ask the user before proceeding.

6.  Bump the version number in the version file.

7.  Update `CHANGELOG.md`:

    - Rename `[Unreleased]` to `[X.Y.Z] - YYYY-MM-DD` (today’s date, ISO
      8601).

    - Add a new empty `[Unreleased]` section at the top.

    - If this is a MAJOR release: rotate the previous major’s entries
      into `CHANGELOG-PREV.x.md` and add an "Older releases" link at the
      bottom of `CHANGELOG.md` (see Changelog Convention).

    - Update the compare links at the bottom:

          [Unreleased]: https://github.com/org/repo/compare/X.Y.Z...HEAD
          [X.Y.Z]: https://github.com/org/repo/compare/PREV...X.Y.Z

8.  Draft the release notes:

    - Write a short introductory sentence summarising the release theme
      (optional but recommended for notable releases).

    - Ensure each entry has a commit hash link; add an issue/PR link
      where applicable.

    - Present the full `[X.Y.Z]` changelog section inside a fenced
      markdown code block for easy review.

    - Do not proceed until the user explicitly approves.

9.  After approval — commit all changes:

        prepare X.Y.Z [skip ci]

10. Push the branch.

11. Create and push the git tag:

    ``` console
    git tag X.Y.Z
    git push origin X.Y.Z
    ```

12. Create the GitHub release using the approved changelog section as
    body. Resolve the token inline as shown above, in the same shell
    invocation:

    ``` shell
    gh_user=$(git config user.name)
    env_var="GH_TOKEN_$(echo "$gh_user" | tr '[:lower:]-' '[:upper:]_')"
    token="${!env_var}"
    if [ -z "$token" ]; then
      echo "Missing $env_var for gh user '$gh_user' — set it before releasing." >&2
      exit 1
    fi
    GH_TOKEN="$token" gh release create X.Y.Z --title "X.Y.Z" --notes "<approved changelog section>"
    ```
