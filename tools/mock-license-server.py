"""Dev only: answers POST /api/v1/heartbeat like the MG License Server (always "fine", or revoked if the
token contains REVOKE). Run inside the dev Docker network as http://itourwp-lic:8080."""
import json
from http.server import BaseHTTPRequestHandler, HTTPServer


class Handler(BaseHTTPRequestHandler):
    def do_POST(self):
        body = json.loads(self.rfile.read(int(self.headers.get('Content-Length', 0))) or b'{}')
        reply = {'revoked': 'REVOKE' in body.get('token', '')}
        print('heartbeat', body.get('hostname'), body.get('installId'), reply, flush=True)
        data = json.dumps(reply).encode()
        self.send_response(200)
        self.send_header('Content-Type', 'application/json')
        self.send_header('Content-Length', str(len(data)))
        self.end_headers()
        self.wfile.write(data)


HTTPServer(('0.0.0.0', 8080), Handler).serve_forever()
