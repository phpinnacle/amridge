use hyper::{Request, Response, Body, Error};
use bytes::{BytesMut, Bytes, BufMut, Buf};

use crate::process::ProcessPool;

pub struct Handler {
    pool: ProcessPool,
}

impl Handler {
    pub fn create(pool: ProcessPool) -> Handler {
        Handler {
            pool,
        }
    }

    pub async fn handle(&self, req: Request<Body>) -> Result<Response<Body>, Error> {
        let mut worker = self.pool.pop();

        let frame_req = self.pack(req);

        worker.write(frame_req).await.expect("No error");

        let mut header = Bytes::from(worker.read(9).await.expect("No error"));

        let flags = header.get_u8();
        let opcode = header.get_u8();
        let stream = header.get_u16();
        let size = header.get_u32() as usize;
        let check = header.get_u8();

        let mut response = Bytes::from(worker.read(size).await.expect("No error"));

        let status = response.get_u16();
        let headers = response.get_u16(); // TODO: headers!
        let length = response.get_u32() as usize;
        let body = response.slice(0..length);

        self.pool.push(worker);

        let response = Response::builder()
            .status(status)
            .body(body.into())
            .unwrap();

        Ok(response)
    }

    fn pack(&self, req: Request<Body>) -> Vec<u8> {
        let method = req.method();
        let uri = req.uri();
        let body = req.body();

        let method_str = method.to_string();
        let method_len = method_str.len();

        let uri_str = uri.to_string();
        let uri_len = uri_str.len();

        let body_str = "".to_string();
        let body_len = body_str.len();

        let size = body_len + 4 + uri_len + 2 + method_len + 1 + 2;

        let mut buf = BytesMut::with_capacity(size);

        // START HEADER
        buf.put_u8(0);
        buf.put_u8(3); // OPCODE_REQUEST
        buf.put_u16(1); // STREAM
        buf.put_u32(size as u32);
        buf.put_u8(1); // CONTROL BIT
        // END HEADER

        // START BODY
        buf.put_u8(method_len as u8);
        buf.put(method_str.as_bytes());
        buf.put_u16(uri_len as u16);
        buf.put(uri_str.as_bytes());
        buf.put_u16(0); // HEADERS!
        buf.put_u32(body_len as u32);
        buf.put(body_str.as_bytes());
        // END BODY

        buf.to_vec()
    }
}
