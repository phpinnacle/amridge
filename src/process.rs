use std::io::Error;
use std::time::{Instant};
use std::process::{Stdio};
use tokio::prelude::*;
use tokio::process::{Command, Child, ChildStdin, ChildStdout};
use std::collections::VecDeque;
use std::sync::Mutex;

#[derive(Debug)]
pub struct ProcessPool {
    num: u32,
    cmd: String,
    args: Vec<String>,
    workers: Mutex<VecDeque<Process>>,
}

impl ProcessPool {
    pub fn new(num: u32, cmd: String, args: Vec<String>) -> ProcessPool {
        ProcessPool {
            num,
            cmd,
            args,
            workers: Mutex::new(VecDeque::with_capacity(num as usize))
        }
    }

    pub fn run(&self) {
        for i in 0..self.num {
            let process = Process::spawn(self.cmd.clone(), self.args.clone());

            self.push(process);
        }
    }

    pub fn pop(&self) -> Process {
        self.workers.lock().expect("No error").pop_front().unwrap()
    }

    pub fn push(&self, process: Process) {
        self.workers.lock().expect("No error").push_back(process);
    }
}

impl Drop for ProcessPool {
    fn drop(&mut self) {
        for worker in self.workers.lock().expect("No error").iter_mut() {
            worker.kill();
        }
    }
}

#[derive(Debug)]
pub struct Process {
    instance: Child,
    stdin: ChildStdin,
    stdout: ChildStdout,
    started: Instant,
}

impl Process {
    pub fn spawn(cmd: String, args: Vec<String>) -> Process {
        let mut instance = Command::new(cmd)
            .args(args)
            .stdin(Stdio::piped())
            .stdout(Stdio::piped())
            .spawn()
            .unwrap();

        let stdin = instance.stdin().take().unwrap();
        let stdout = instance.stdout().take().unwrap();
        Process {
            instance,
            stdin,
            stdout,
            started: Instant::now(),
        }
    }

//    pub fn mon(&self) {
//        let child = self.instance;
//
//        tokio::spawn(async move {
//            let mut interval = time::interval(Duration::from_secs(3));
//
//            loop {
//                interval.tick().await;
//
//                println!("{}", child.id());
//            }
//        });
//    }

    pub fn pid(&self) -> u32 {
        self.instance.id()
    }

    pub fn kill(&mut self) {
        self.instance.kill().expect("No Error");
    }

    pub async fn write(&mut self, data: Vec<u8>) -> Result<(), Error> {
        Ok(self.stdin.write_all(data.as_ref()).await?)
    }

    pub async fn read(&mut self, num: usize) -> Result<Vec<u8>, Error> {
        let mut out = vec![0u8; num];

        self.stdout.read_exact(&mut out).await?;

        Ok(out)
    }
}

impl Drop for Process {
    fn drop(&mut self) {
        self.kill();
    }
}

impl Into<u32> for Process {
    fn into(self) -> u32 {
        self.pid()
    }
}
