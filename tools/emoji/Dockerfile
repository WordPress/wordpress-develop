# Build environment for the Twemoji COLRv1 font.
#
# The same image gives the same tools on Windows, macOS and Linux. It is for
# maintainers updating Twemoji, not for sites: sites serve the built WOFF2.
FROM python:3.12-slim@sha256:2f17fc044b579bab302c2e8054d3a686e2cb9a83de48e70534b94cd8ebbe06a9

COPY requirements.lock /tmp/requirements.lock
RUN pip install --no-cache-dir --no-deps -r /tmp/requirements.lock

WORKDIR /work
ENTRYPOINT ["python", "/work/build-twemoji-colrv1.py"]
