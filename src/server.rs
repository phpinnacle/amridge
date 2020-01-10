use bytes::{BytesMut, Bytes, BufMut, Buf};
use hyper::{Request, Response, Body, Error};
use hyper::service::{Service};
use std::sync::{Arc, Mutex};
use std::task::{Context, Poll};
use core::future::Future;

use crate::process::ProcessPool;
use crate::config::Config;
use std::pin::Pin;
use futures_util::FutureExt;

#[derive(Debug)]
pub struct Server {
    config: Config,
}

impl Server {
    pub fn new(config: Config) -> Server {
        Server {
            config,
        }
    }

    pub async fn serve(&self) {
        let pool = Arc::new(ProcessPool::new(
            self.config.pool.workers.clone(),
            self.config.pool.interpreter.clone(),
            vec![self.config.pool.command.clone()]
        ));

        let addr = ([127, 0, 0, 1], 3000).into();
        let server = hyper::Server::bind(&addr).serve(Connection {
            pool: pool.clone(),
        });

        if let Err(e) = server.await {
            eprintln!("Server Error: {}", e);
        }
    }
}

#[derive(Debug, Clone)]
struct Connection {
    pool: Arc<ProcessPool>,
}

impl<T> Service<T> for Connection {
    type Response = Handler;
    type Error = std::io::Error;
    type Future = Pin<Box<dyn Future<Output = Result<Self::Response, Self::Error>> + Send>>;

    fn poll_ready(&mut self, _cx: &mut Context<'_>) -> Poll<Result<(), Self::Error>> {
        Ok(()).into()
    }

    fn call(&mut self, _: T) -> Self::Future {
        let pool = self.pool.clone();

        async move { Ok(Handler::new(pool.clone())) }.boxed()
    }
}

#[derive(Debug, Clone)]
struct Handler {
    pool: Arc<ProcessPool>,
}

impl Handler {
    fn new(pool: Arc<ProcessPool>) -> Handler {
        Handler {
            pool
        }
    }

    async fn handle(&mut self, req: Request<Body>) -> Result<Response<Body>, Error> {
//        let mut worker = self.pool.pop();
//
//        let frame_req = self.pack(req);
//
//        worker.write(frame_req).await.expect("No error");
//
//        let mut header = Bytes::from(worker.read(9).await.expect("No error"));
//
//        let flags = header.get_u8();
//        let opcode = header.get_u8();
//        let stream = header.get_u16();
//        let size = header.get_u32() as usize;
//        let check = header.get_u8();
//
//        let mut response = Bytes::from(worker.read(size).await.expect("No error"));
//
//        let status = response.get_u16();
//        let headers = response.get_u16(); // TODO: headers!
//        let length = response.get_u32() as usize;
//        let body = response.slice(0..length);
//
//        self.pool.push(worker);

        Ok(Response::builder()
            .status(200)
            .body("Hello".into())
            .unwrap())
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

impl Service<Request<Body>> for Handler {
    type Response = Response<Body>;
    type Error = Error;
    type Future = Pin<Box<dyn Future<Output = Result<Self::Response, Self::Error>> + Send>>;

    fn poll_ready(&mut self, _cx: &mut Context<'_>) -> Poll<Result<(), Self::Error>> {
        Ok(()).into()
    }

    fn call(&mut self, req: Request<Body>) -> Self::Future {
        let mut clone = self.clone();

        async move { clone.handle(req).await }.boxed()
    }
}

